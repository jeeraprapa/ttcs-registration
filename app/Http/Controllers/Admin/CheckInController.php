<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EventSession;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function scan(): View
    {
        Gate::authorize('manage-admin');

        return view('admin.check-ins.scan');
    }

    public function resolve(Request $request): JsonResponse
    {
        Gate::authorize('manage-admin');
        $data = $request->validate(['qr' => ['required', 'string', 'max:4096']]);
        $url = trim($data['qr']);
        $parts = parse_url($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages(['qr' => 'Please scan a valid registration QR code.']);
        }
        $reference = basename($parts['path'] ?? '');
        if (! Str::isUuid($reference)) {
            throw ValidationException::withMessages(['qr' => 'Please scan a valid registration QR code.']);
        }
        $expectedPath = parse_url(route('admin.check-ins.show', ['registration' => $reference]), PHP_URL_PATH);
        $signedRequest = Request::create($url);
        if (($parts['path'] ?? '') !== $expectedPath
            || ! is_string($signedRequest->query('signature')) || ! URL::hasValidSignature($signedRequest)) {
            throw ValidationException::withMessages(['qr' => 'This QR code is invalid or has expired. Please check the booking in Registrations.']);
        }
        $registration = Registration::where('reference', $reference)->first();
        if (! $registration) {
            throw ValidationException::withMessages(['qr' => 'The booking for this QR code could not be found.']);
        }

        return response()->json([
            'url' => URL::signedRoute('admin.check-ins.show', ['registration' => $registration->reference]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Registration $registration): View
    {
        Gate::authorize('manage-admin');

        return view('admin.check-ins.show', compact('registration'));
    }

    public function store(Request $request, Registration $registration): RedirectResponse
    {
        Gate::authorize('manage-admin');
        DB::transaction(function () use ($request, $registration): void {
            EventSession::whereKey($registration->event_session_id)->lockForUpdate()->firstOrFail();
            $booking = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($booking->cancelled_at) {
                throw ValidationException::withMessages(['registration' => 'Cancelled bookings cannot check in.']);
            }
            if ($booking->checked_in_at) {
                return;
            }
            $booking->update(['checked_in_at' => now(), 'checked_in_by' => $request->user('admin')->id]);
            AdminAuditLog::create(['admin_user_id' => $request->user('admin')->id, 'action' => 'registration.check_in',
                'subject_type' => 'registration', 'subject_id' => $booking->id, 'metadata' => [], 'created_at' => now()]);
        });

        return back()->with('status', 'Guest checked in.');
    }
}
