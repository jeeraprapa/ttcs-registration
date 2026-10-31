<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfirmationEmail;
use App\Models\EventSession;
use App\Models\Registration;
use App\Support\RegistrationDomains;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('manage-admin');

        $urlCounts = Registration::selectRaw('registration_url, COUNT(*) as bookings, SUM(guest_count) as guests')
            ->groupBy('registration_url')->get()->keyBy('registration_url');
        $registrationsByUrl = collect(RegistrationDomains::REGISTRATION_URLS)->map(function (string $url) use ($urlCounts): array {
            $counts = collect([$urlCounts->get($url), $urlCounts->get(rtrim($url, '/'))])->filter();

            return ['url' => $url, 'bookings' => (int) $counts->sum('bookings'), 'guests' => (int) $counts->sum('guests')];
        });

        return view('admin.dashboard', [
            'registrationCount' => Registration::whereNull('cancelled_at')->count(), 'guestCount' => Registration::whereNull('cancelled_at')->sum('guest_count'),
            'checkedInCount' => Registration::whereNull('cancelled_at')->whereNotNull('checked_in_at')->count(),
            'sessions' => EventSession::withCount(['registrations' => fn ($query) => $query->whereNull('cancelled_at')])->withSum(['registrations' => fn ($query) => $query->whereNull('cancelled_at')], 'guest_count')->orderBy('local_date')->orderBy('local_start_time')->get(),
            'emailCounts' => ConfirmationEmail::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'registrationsByUrl' => $registrationsByUrl,
        ]);
    }
}
