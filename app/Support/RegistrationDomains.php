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
        return EventSession::query()->where('event_key', EventSchedule::EVENT_KEY)
            ->whereIn('local_date', EventSchedule::dates())->whereIn('local_start_time', EventSchedule::TIMES);
    }
}
