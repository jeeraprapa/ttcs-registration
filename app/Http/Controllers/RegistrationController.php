<?php

namespace App\Http\Controllers;

use App\Actions\RegisterForSession;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Registration;
use App\Support\RegistrationDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationController extends Controller
{
    public function create(Request $request)
    {
        $tokens = array_filter($request->session()->get('registration_tokens', []), fn ($expires) => $expires >= time());
        // Keep recently submitted tokens for response-loss retries, with a bounded session footprint.
        $token = $request->old('idempotency_token');
        if (! is_string($token) || ! isset($tokens[hash('sha256', $token)])) {
            $token = bin2hex(random_bytes(32));
        }
        $tokens[hash('sha256', $token)] = time() + 86400;
        if (count($tokens) > 20) {
            $tokens = array_slice($tokens, -20, null, true);
        }
        $request->session()->put('registration_tokens', $tokens);
        $policies = array_intersect_key($request->session()->get('registration_form_policies', []), $tokens);
        $policies[hash('sha256', $token)] ??= config('registration.consents');
        $request->session()->put('registration_form_policies', $policies);

        return response()->view('public.registration', [
            'sessions' => app(RegistrationDomains::class)->sessions($request->getHost())->orderBy('local_date')->orderBy('local_start_time')->get(),
            'idempotencyToken' => $token, 'titles' => config('registration.titles'), 'countries' => config('registration.countries'),
            'referralSources' => config('registration.referral_sources'), 'consents' => $policies[hash('sha256', $token)],
        ])->header('Cache-Control', 'no-store');
    }

    public function store(StoreRegistrationRequest $request, RegisterForSession $action)
    {
        $data = $request->validated();
        if (! app(RegistrationDomains::class)->sessions($request->getHost())->whereKey($data['event_session_id'])->exists()) {
            throw ValidationException::withMessages(['event_session_id' => 'Please select a session available on this registration website.']);
        }
        $data['registration_url'] = $request->getSchemeAndHttpHost().$request->getBaseUrl().'/';
        $hash = hash('sha256', $data['idempotency_token']);
        $expires = $request->session()->get('registration_tokens', [])[$hash] ?? 0;
        if ($expires < time()) {
            return back()->withErrors(['idempotency_token' => 'Your form has expired. Please refresh and try again.'])->withInput();
        }
        $data['_consent_definitions'] = $request->session()->get('registration_form_policies', [])[$hash] ?? config('registration.consents');
        try {
            $registration = $action->execute($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Do not report a raw database exception: its bindings may contain personal information.
            Log::error('Registration storage failed.', ['exception_type' => get_class($exception)]);

            return back()->withErrors(['registration' => 'We could not complete your registration. Please try again using this form.'])->withInput();
        }
        $request->session()->put('confirmed_registration_id', $registration->id);

        // The durable pending email record is dispatched by the scheduler; no queue failure can undo this booking.
        return redirect()->route('registration.thank-you');
    }

    public function thankYou(Request $request)
    {
        $registration = Registration::with(['confirmationEmails', 'consents.definition'])->find($request->session()->get('confirmed_registration_id'));
        if (! $registration) {
            return redirect()->route('registration.create');
        }

        $trackedReferences = $request->session()->get('registration_tracked_references', []);
        $submissionId = null;
        if (! isset($trackedReferences[$registration->reference])) {
            $submissionId = $registration->reference;
            $trackedReferences[$submissionId] = true;
            $request->session()->put('registration_tracked_references', $trackedReferences);
        }
        $request->session()->forget('registration_tracking_reference');

        return response()->view('public.thank-you', compact('registration', 'submissionId'))->header('Cache-Control', 'no-store');
    }
}
