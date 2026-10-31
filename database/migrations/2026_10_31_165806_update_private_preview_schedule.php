<?php

use App\Support\EventSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('event_sessions')->where('event_key', EventSchedule::EVENT_KEY)
                ->whereNotIn('local_date', EventSchedule::dates())->update(['is_open' => false]);
            foreach (EventSchedule::dates() as $date) {
                foreach (EventSchedule::TIMES as $time) {
                    DB::table('event_sessions')->insertOrIgnore([
                        'event_key' => EventSchedule::EVENT_KEY, 'local_date' => $date, 'local_start_time' => $time,
                        'timezone' => config('registration.timezone'), 'venue' => config('registration.venue'),
                        'is_open' => true, 'capacity' => null, 'reserved_seats' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve sessions and historical bookings when rolling back.
    }
};
