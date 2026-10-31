<?php

namespace App\Support;

use App\Models\EventSession;
use Illuminate\Database\Eloquent\Builder;

class RegistrationDomains
{
    public const array REGISTRATION_URLS = [
        'https://registration.uhhk.uhresidencesbangkok.com/',
        'https://registration.sprops.hk.uhresidencesbangkok.com/',
        'https://registration.hk.uhresidencesbangkok.com/',
    ];

    public function sessions(string $host): Builder
    {
        $query = EventSession::query();
        $special = config('registration.domain_sessions.special');
        if ($host === config('registration.domain_sessions.special_host')) {
            return $query->where('local_date', $special['date'])->where('local_start_time', $special['time']);
        }
        if ($host === config('registration.domain_sessions.public_host')) {
            return $query->where(fn ($query) => $query->where('local_date', '!=', $special['date'])
                ->orWhere('local_start_time', '!=', $special['time']));
        }
        if (in_array($host, config('registration.domain_sessions.preview_hosts'), true)) {
            return $query;
        }
        abort(404);
    }
}
