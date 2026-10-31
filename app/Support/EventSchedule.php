<?php

namespace App\Support;

class EventSchedule
{
    public const string EVENT_KEY = 'uh-hk-preview-2026';

    public const array TIMES = ['10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'];

    public static function dates(): array
    {
        return array_merge(
            array_map(fn (int $day): string => sprintf('2026-11-%02d', $day), [18, 19, 20, 21, 22, 25, 26, 27, 28, 29]),
            array_map(fn (int $day): string => sprintf('2026-12-%02d', $day), [1, 2, 3, 4, 5, 6]),
        );
    }
}
