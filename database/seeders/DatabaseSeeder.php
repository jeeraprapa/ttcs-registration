<?php

namespace Database\Seeders;

use App\Models\ConsentDefinition;
use App\Models\EventSession;
use App\Support\EventSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        foreach (EventSchedule::dates() as $date) {
            foreach (EventSchedule::TIMES as $time) {
                EventSession::firstOrCreate(['event_key' => 'uh-hk-preview-2026', 'local_date' => $date, 'local_start_time' => $time],
                    ['timezone' => config('registration.timezone'), 'venue' => config('registration.venue'), 'is_open' => true, 'capacity' => null]);
            }
        }
        foreach (config('registration.consents') as $code => $consent) {
            $version = $consent['version'].'-'.substr(hash('sha256', $consent['text']), 0, 12);
            ConsentDefinition::firstOrCreate(['code' => $code, 'version' => $version], ['text_snapshot' => $consent['text']]);
        }
    }
}
