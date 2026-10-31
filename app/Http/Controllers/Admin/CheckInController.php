<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\EventSession;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckInController extends Controller
{
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
