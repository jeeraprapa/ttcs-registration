<?php

namespace Tests\Feature;

use App\Actions\RegisterForSession;
use App\Jobs\SendRegistrationConfirmation;
use App\Models\ConfirmationEmail;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\RegistrationConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'), 'These integration tests require MySQL.');
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'), 'Use a dedicated test database.');
        $this->frameworkRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload(array $replace = []): array
    {
        $session = EventSession::orderBy('id')->first();
        $token = str_repeat('a', 64);
        $this->withSession(['registration_tokens' => [hash('sha256', $token) => time() + 3600]]);

        return array_replace([
            'idempotency_token' => $token, 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 2,
            'title' => 'Mr.', 'first_name' => 'Test', 'last_name' => 'Guest',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'test@example.com', 'referral_source' => 'Others',
            'consents' => ['privacy' => '1'],
        ], $replace);
    }

    public function test_booking_saves_snapshots_separate_consents_and_pending_email(): void
    {
        $this->post(route('registration.store'), $this->payload())->assertRedirect(route('registration.thank-you'));
        $booking = Registration::firstOrFail();
        $this->assertSame(2, $booking->seat_count);
        $this->assertSame(2, $booking->eventSession->reserved_seats);
        $this->assertCount(4, $booking->consents);
        $this->assertTrue($booking->consents()->whereHas('definition', fn ($q) => $q->where('code', 'privacy'))->first()->accepted);
        $this->assertFalse($booking->consents()->whereHas('definition', fn ($q) => $q->where('code', 'marketing_direct'))->first()->accepted);
        $this->assertNull($booking->consents()->whereHas('definition', fn ($q) => $q->where('code', 'mainland_transfer'))->first()->accepted);
        $this->assertSame('pending', $booking->confirmationEmails->first()->status);
        $this->withSession(['registration_tracking_reference' => null]);
        $this->get(route('registration.thank-you'))->assertOk()
            ->assertViewHas('registration', fn ($value) => $value->id === $booking->id)
            ->assertViewHas('submissionId', $booking->reference)
            ->assertSessionHas('registration_tracked_references.'.$booking->reference, true)
            ->assertSee("event: 'registration'", false)
            ->assertSee($booking->reference);
        $this->get(route('registration.thank-you'))->assertOk()
            ->assertViewHas('submissionId', null)
            ->assertDontSee("event: 'registration'", false);
        $this->post(route('registration.store'), $this->payload())->assertRedirect(route('registration.thank-you'));
        $this->get(route('registration.thank-you'))->assertOk()
            ->assertDontSee("event: 'registration'", false);
    }

    public function test_uhhk_domain_allows_every_session_and_records_its_source(): void
    {
        $host = 'https://registration.uhhk.uhresidencesbangkok.com';
        $sessions = EventSession::orderBy('local_date')->orderBy('local_start_time')->get();
        $this->get($host.'/')->assertOk()
            ->assertViewHas('sessions', fn ($available) => $available->modelKeys() === $sessions->modelKeys())
            ->assertSee('SELECT YOUR PREFERRED SESSION');

        foreach ($sessions as $session) {
            $token = hash('sha256', 'uhhk-session-'.$session->id);
            $data = $this->payload([
                'idempotency_token' => $token,
                'event_session_id' => $session->id,
                'selected_date' => $session->local_date->format('Y-m-d'),
            ]);
            $this->withSession(['registration_tokens' => [hash('sha256', $token) => time() + 3600]]);
            $this->post($host.'/registrations', $data)->assertRedirect(route('registration.thank-you'));
            $this->assertDatabaseHas('registrations', [
                'event_session_id' => $session->id,
                'registration_url' => $host.'/',
            ]);
        }
    }

    public function test_domains_filter_sessions_and_reject_other_domains_sessions(): void
    {
        $specialHost = 'https://registration.sprops.hk.uhresidencesbangkok.com';
        $publicHost = 'https://registration.hk.uhresidencesbangkok.com';
        $special = EventSession::where('local_date', '2026-11-14')->where('local_start_time', '11:30:00')->firstOrFail();
        $other = EventSession::where('id', '!=', $special->id)->firstOrFail();
        $this->get($specialHost.'/')->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->modelKeys() === [$special->id])
            ->assertSee('14 November 2026 | 11.00am')
            ->assertSee('Please complete the form to confirm your attendance.')
            ->assertDontSee('SELECT YOUR PREFERRED SESSION')
            ->assertSeeInOrder(['Mobile number', 'Number of guests', 'How did you hear about us?'])
            ->assertSee('name="event_session_id" value="'.$special->id.'"', false)
            ->assertSee('id="guest_count" name="guest_count" required', false)
            ->assertDontSee('type="hidden" name="guest_count"', false);
        $this->get($publicHost.'/')->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 3 && ! $sessions->contains('id', $special->id))
            ->assertSee('SELECT YOUR PREFERRED SESSION')
            ->assertSee('Please complete the form below to register for your preferred session.');
        $this->get('http://uh-registration.test/')->assertOk()->assertViewHas('sessions', fn ($sessions) => $sessions->count() === 4);
        $this->get('https://unconfigured.example/')->assertNotFound();
        $this->post($specialHost.'/registrations', $this->payload(['event_session_id' => $other->id]))->assertSessionHasErrors('event_session_id');
        $this->post($publicHost.'/registrations', $this->payload(['event_session_id' => $special->id]))->assertSessionHasErrors('event_session_id');
        $this->assertDatabaseCount('registrations', 0);
        $this->post($specialHost.'/registrations', $this->payload(['registration_url' => 'https://forged.example/']))->assertRedirect();
        $booking = Registration::firstOrFail();
        $this->assertSame($specialHost.'/', $booking->registration_url);
        $this->assertSame('2026-11-14', $booking->session_date_snapshot->format('Y-m-d'));
        $this->assertSame('11:30:00', $booking->session_time_snapshot);
        $this->post($specialHost.'/registrations', $this->payload())->assertRedirect();
        $this->assertDatabaseCount('registrations', 1);
        $publicData = $this->payload([
            'idempotency_token' => str_repeat('b', 64), 'event_session_id' => $other->id,
            'selected_date' => $other->local_date->format('Y-m-d'),
        ]);
        $this->withSession(['registration_tokens' => [hash('sha256', str_repeat('b', 64)) => time() + 3600]]);
        $this->post($publicHost.'/registrations', $publicData)->assertRedirect();
        $this->assertDatabaseHas('registrations', ['event_session_id' => $other->id, 'registration_url' => $publicHost.'/']);
        $localData = $this->payload(['idempotency_token' => str_repeat('c', 64)]);
        $this->withSession(['registration_tokens' => [hash('sha256', str_repeat('c', 64)) => time() + 3600]]);
        $this->post('http://uh-registration.test/registrations', $localData)->assertRedirect();
        $this->assertDatabaseHas('registrations', ['registration_url' => 'http://uh-registration.test/']);
    }

    public function test_replay_after_response_loss_and_session_closed_returns_same_booking(): void
    {
        $data = $this->payload();
        $this->post(route('registration.store'), $data)->assertRedirect();
        EventSession::whereKey($data['event_session_id'])->update(['is_open' => false]);
        $this->withSession(['confirmed_registration_id' => null]);
        $this->post(route('registration.store'), $data)->assertRedirect(route('registration.thank-you'));
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('confirmation_emails', 1);
        $this->assertDatabaseCount('registration_consents', 4);
        $this->assertSame(2, EventSession::find($data['event_session_id'])->reserved_seats);
    }

    public function test_reusing_token_with_different_payload_is_rejected(): void
    {
        $data = $this->payload();
        $this->post(route('registration.store'), $data)->assertRedirect();
        $this->post(route('registration.store'), [...$data, 'guest_count' => 3])->assertSessionHasErrors('idempotency_token');
        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_invalid_pairing_and_guests_and_country_are_rejected(): void
    {
        $this->post(route('registration.store'), $this->payload(['selected_date' => '2026-11-15']))->assertSessionHasErrors('event_session_id');
        $this->post(route('registration.store'), $this->payload(['guest_count' => 5]))->assertSessionHasErrors('guest_count');
        $this->post(route('registration.store'), $this->payload(['phone_dial_code' => '+66']))->assertSessionHasErrors('phone_country_code');
        $this->post(route('registration.store'), $this->payload(['consents' => []]))->assertSessionHasErrors('consents.privacy');
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_confirmation_requires_browser_ownership(): void
    {
        $data = $this->payload();
        $this->post(route('registration.store'), $data)->assertRedirect();
        $this->withSession(['confirmed_registration_id' => null, 'registration_tokens' => []]);
        $this->get(route('registration.thank-you'))->assertRedirect(route('registration.create'));
        $this->post(route('registration.store'), $data)->assertSessionHasErrors('idempotency_token');
    }

    public function test_failure_in_consent_creation_rolls_back_booking_counter_and_outbox(): void
    {
        $data = $this->payload();
        RegistrationConsent::creating(fn () => throw new \RuntimeException('Synthetic failure'));
        try {
            app(RegisterForSession::class)->execute($data);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        } finally {
            RegistrationConsent::flushEventListeners();
        }
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('confirmation_emails', 0);
        $this->assertSame(0, EventSession::find($data['event_session_id'])->reserved_seats);
    }

    public function test_local_mail_preview_and_duplicate_worker_do_not_send_real_email(): void
    {
        Mail::fake();
        $this->post(route('registration.store'), $this->payload());
        $record = ConfirmationEmail::firstOrFail();
        (new SendRegistrationConfirmation($record->id))->handle();
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertSame('local_preview', $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->attempts);
        Mail::assertNothingSent();
    }

    public function test_mail_failure_keeps_committed_booking_and_schedules_retry(): void
    {
        $this->post(route('registration.store'), $this->payload());
        config(['registration.mail_mode' => 'delivery']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Synthetic transport failure'));
        $record = ConfirmationEmail::firstOrFail();
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertDatabaseCount('registrations', 1);
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertTrue($record->fresh()->next_attempt_at->isFuture());
        $this->assertSame('confirmation_transport_error', $record->fresh()->last_error_code);
    }

    public function test_pending_dispatcher_recovers_an_expired_worker_lease(): void
    {
        Queue::fake();
        $this->post(route('registration.store'), $this->payload());
        $record = ConfirmationEmail::firstOrFail();
        $record->update(['status' => 'processing', 'lease_until' => now()->subMinute()]);
        $this->artisan('confirmations:dispatch')->assertExitCode(0);
        Queue::assertPushed(SendRegistrationConfirmation::class, fn ($job) => $job->confirmationEmailId === $record->id);
    }

    public function test_csrf_is_required_for_public_registration(): void
    {
        $data = $this->payload();
        $this->app['env'] = 'local';
        $this->post(route('registration.store'), $data)->assertStatus(419);
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_consent_snapshot_matches_the_form_shown_before_configuration_changed(): void
    {
        $this->get(route('registration.create'))->assertOk();
        $definitions = config('registration.consents');
        $data = $this->payload();
        $hash = hash('sha256', $data['idempotency_token']);
        $this->withSession(['registration_form_policies' => [$hash => $definitions]]);
        config(['registration.consents.privacy.text' => 'A different policy after the form was opened.']);
        $this->post(route('registration.store'), $data)->assertRedirect(route('registration.thank-you'));
        $privacy = Registration::firstOrFail()->consents()->whereHas('definition', fn ($q) => $q->where('code', 'privacy'))->first();
        $this->assertSame($definitions['privacy']['text'], $privacy->definition->text_snapshot);
    }

    public function test_mainland_consent_is_visible_and_acceptance_is_saved_without_extra_checkbox(): void
    {
        $this->get(route('registration.create'))->assertOk()
            ->assertSee(config('registration.consents.mainland_transfer.text'))
            ->assertDontSee('I am a Chinese Mainland customer.');
        $this->post(route('registration.store'), $this->payload([
            'consents' => ['privacy' => '1', 'marketing_direct' => '1', 'marketing_sharing' => '1', 'mainland_transfer' => '1'],
        ]))->assertRedirect(route('registration.thank-you'));
        $consent = Registration::firstOrFail()->consents()
            ->whereHas('definition', fn ($query) => $query->where('code', 'mainland_transfer'))->firstOrFail();
        $this->assertTrue($consent->is_applicable);
        $this->assertTrue($consent->accepted);
    }
}
