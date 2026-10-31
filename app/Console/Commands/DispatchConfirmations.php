<?php

namespace App\Console\Commands;

use App\Jobs\SendRegistrationConfirmation;
use App\Models\ConfirmationEmail;
use Illuminate\Console\Command;

class DispatchConfirmations extends Command
{
    protected $signature = 'confirmations:dispatch';

    protected $description = 'Queue pending confirmations and recover expired worker leases.';

    public function handle(): int
    {
        ConfirmationEmail::where(function ($query) {
            $query->where(function ($pending) {
                $pending->where('status', 'pending')->where(function ($due) {
                    $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                });
            })
                ->orWhere(function ($expired) {
                    $expired->where('status', 'processing')->where('lease_until', '<=', now());
                });
        })->orderBy('id')->chunkById(100, function ($records) {
            foreach ($records as $record) {
                SendRegistrationConfirmation::dispatch($record->id);
            }
        });
        $this->info('Pending confirmation jobs dispatched.');

        return self::SUCCESS;
    }
}
