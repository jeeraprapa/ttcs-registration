<?php

namespace App\Actions;

use App\Models\ConsentDefinition;
use App\Models\EventSession;
use App\Models\Registration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterForSession
{
    public function execute(array $input): Registration
    {
        $tokenHash = hash('sha256', $input['idempotency_token']);
        $definitions = $input['_consent_definitions'] ?? config('registration.consents');
        $data = [
            'event_session_id' => (int) $input['event_session_id'], 'selected_date' => $input['selected_date'],
            'guest_count' => (int) $input['guest_count'], 'title' => $input['title'], 'first_name' => $input['first_name'],
            'last_name' => $input['last_name'], 'phone_country_code' => $input['phone_country_code'],
            'phone_dial_code' => $input['phone_dial_code'], 'phone_number' => $input['phone_number'],
            'email' => $input['email'], 'referral_source' => $input['referral_source'],
            'mainland_customer' => (bool) ($input['mainland_customer'] ?? false),
            'consents' => [],
        ];
        if (isset($input['registration_url'])) {
            $data['registration_url'] = $input['registration_url'];
        }
        foreach (['privacy', 'marketing_direct', 'marketing_sharing', 'mainland_transfer'] as $code) {
            $data['consents'][$code] = (bool) ($input['consents'][$code] ?? false);
        }
        $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if ($existing = Registration::where('idempotency_key', $tokenHash)->first()) {
            return $this->replay($existing, $fingerprint);
        }
        if (! app()->environment(['local', 'testing']) && ! config('registration.policy_confirmed')) {
            throw ValidationException::withMessages(['registration' => 'Registration policies require confirmation before public launch.']);
        }
        try {
            return DB::transaction(function () use ($data, $fingerprint, $tokenHash, $definitions) {
                $session = EventSession::whereKey($data['event_session_id'])->lockForUpdate()->first();
                // Current read prevents a stale repeatable-read snapshot after waiting for a concurrent booking.
                $existing = Registration::where('idempotency_key', $tokenHash)->lockForUpdate()->first();
                if ($existing) {
                    return $this->replay($existing, $fingerprint);
                }
                $this->validateBusinessInput($data, $definitions);
                if (! $session || $session->local_date->format('Y-m-d') !== $data['selected_date']) {
                    throw ValidationException::withMessages(['event_session_id' => 'Please select a valid session for your preferred day.']);
                }
                if (! $session->is_open) {
                    throw ValidationException::withMessages(['event_session_id' => 'This session is currently closed for registration.']);
                }
                $include = config('registration.guests_include_registrant');
                $seats = is_bool($include) ? $data['guest_count'] + ($include ? 0 : 1) : null;
                if ($session->capacity !== null && ($seats === null || $session->reserved_seats + $seats > $session->capacity)) {
                    throw ValidationException::withMessages(['event_session_id' => 'This session has no remaining availability. Please select another session.']);
                }
                $registration = Registration::create([
                    'reference' => (string) Str::uuid(), 'event_session_id' => $session->id,
                    'registration_url' => $data['registration_url'] ?? null,
                    ...array_intersect_key($data, array_flip(['title', 'first_name', 'last_name', 'phone_country_code', 'phone_dial_code', 'phone_number', 'email', 'referral_source', 'guest_count'])),
                    'seat_count' => $seats, 'session_date_snapshot' => $session->local_date->format('Y-m-d'),
                    'session_time_snapshot' => $session->local_start_time, 'timezone_snapshot' => $session->timezone,
                    'venue_snapshot' => $session->venue, 'idempotency_key' => $tokenHash, 'request_fingerprint' => $fingerprint,
                ]);
                foreach ($definitions as $code => $definition) {
                    $version = $definition['version'].'-'.substr(hash('sha256', $definition['text']), 0, 12);
                    $record = ConsentDefinition::firstOrCreate(['code' => $code, 'version' => $version], ['text_snapshot' => $definition['text']]);
                    $applicable = $code !== 'mainland_transfer' || $data['mainland_customer'];
                    $registration->consents()->create(['consent_definition_id' => $record->id, 'is_applicable' => $applicable,
                        'accepted' => $applicable ? $data['consents'][$code] : null, 'recorded_at' => now()]);
                }
                $registration->confirmationEmails()->create(['status' => 'pending', 'next_attempt_at' => now()]);
                if ($seats !== null) {
                    $session->increment('reserved_seats', $seats);
                }

                return $registration;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Registration::where('idempotency_key', $tokenHash)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $fingerprint);
        }
    }

    private function replay(Registration $registration, string $fingerprint): Registration
    {
        if (! hash_equals($registration->request_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['idempotency_token' => 'This form was already submitted with different details. Please start a new registration.']);
        }

        return $registration;
    }

    private function validateBusinessInput(array $data, array $definitions): void
    {
        $errors = [];
        if (! in_array($data['title'], config('registration.titles'), true)) {
            $errors['title'] = 'Please select a valid title.';
        }
        if (! in_array($data['referral_source'], config('registration.referral_sources'), true)) {
            $errors['referral_source'] = 'Please select a valid referral source.';
        }
        $country = collect(config('registration.countries'))->firstWhere('code', $data['phone_country_code']);
        if (! $country || $country['dial_code'] !== $data['phone_dial_code']) {
            $errors['phone_country_code'] = 'Please select a matching country and calling code.';
        }
        foreach ($definitions as $code => $definition) {
            if ($definition['required'] && ($code !== 'mainland_transfer' || $data['mainland_customer']) && ! $data['consents'][$code]) {
                $errors['consents.'.$code] = 'Please accept this required acknowledgement.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
