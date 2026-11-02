<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfirmationEmail;
use App\Models\Registration;
use App\Support\RegistrationDomains;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('manage-admin');
        $sessions = app(RegistrationDomains::class)->sessions('')
            ->withCount(['registrations' => fn ($query) => $query->whereNull('cancelled_at')])
            ->withSum(['registrations' => fn ($query) => $query->whereNull('cancelled_at')], 'guest_count')
            ->orderBy('local_date')->orderBy('local_start_time')->get();
        $sessionIds = $sessions->modelKeys();
        $active = Registration::whereIn('event_session_id', $sessionIds)->whereNull('cancelled_at');
        $bookedSessionCount = $sessions->filter(fn ($session) => $session->registrations_count > 0)->count();
        $availableSessionCount = $sessions->filter(fn ($session) => $session->is_open && $session->registrations_count === 0)->count();

        return view('admin.dashboard', [
            'registrationCount' => (clone $active)->count(), 'guestCount' => (clone $active)->sum('guest_count'),
            'checkedInCount' => (clone $active)->whereNotNull('checked_in_at')->count(),
            'cancelledCount' => Registration::whereIn('event_session_id', $sessionIds)->whereNotNull('cancelled_at')->count(),
            'sessions' => $sessions, 'bookedSessionCount' => $bookedSessionCount,
            'availableSessionCount' => $availableSessionCount,
            'closedSessionCount' => $sessions->count() - $bookedSessionCount - $availableSessionCount,
            'emailCounts' => ConfirmationEmail::whereHas('registration', fn ($query) => $query->whereIn('event_session_id', $sessionIds)->whereNull('cancelled_at'))
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'recentRegistrations' => Registration::whereIn('event_session_id', $sessionIds)->latest('id')->limit(5)->get(),
        ]);
    }
}
