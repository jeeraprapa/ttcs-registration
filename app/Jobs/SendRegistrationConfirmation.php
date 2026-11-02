<?php

namespace App\Jobs;

use App\Mail\RegistrationConfirmation;
use App\Models\ConfirmationEmail;
use App\Support\MailDeliveryLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendRegistrationConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $confirmationEmailId) {}

    public function handle(): void
    {
        $logger = app(MailDeliveryLogger::class);
        $logId = null;
        $claim = (string) Str::uuid();
        $record = DB::transaction(function () use ($claim, $logger, &$logId) {
            $email = ConfirmationEmail::whereKey($this->confirmationEmailId)->lockForUpdate()->first();
            if (! $email || in_array($email->status, ['provider_accepted', 'local_preview', 'failed', 'cancelled'], true)) {
                return null;
            }
            if ($email->registration->cancelled_at) {
                $email->update(['status' => 'cancelled', 'lease_until' => null, 'processing_token' => null]);

                return null;
            }
            if ($email->status === 'processing' && $email->lease_until?->isFuture()) {
                return null;
            }
            if ($email->next_attempt_at?->isFuture()) {
                return null;
            }
            DB::table('mail_delivery_logs')->where('confirmation_email_id', $email->id)
                ->where('status', 'processing')->update(['status' => 'unknown', 'finished_at' => now(), 'updated_at' => now()]);
            $logId = $logger->start([
                'type' => 'confirmation', 'confirmation_email_id' => $email->id,
                'recipient' => $email->registration->email, 'from_address' => config('mail.from.address'),
                'subject' => 'Your Upper House Residences Private Preview registration',
                'mailer' => config('mail.default'), 'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => (int) config('mail.mailers.smtp.port'), 'attempt' => $email->attempts + 1,
            ]);
            $email->update(['status' => 'processing', 'attempts' => $email->attempts + 1, 'lease_until' => now()->addMinutes(5), 'processing_token' => $claim]);

            return $email;
        });
        if (! $record) {
            return;
        }
        try {
            if (config('registration.mail_mode') === 'local') {
                // No message body or contacts are written to logs in local preview mode.
                Log::info('Registration confirmation preview prepared.', ['confirmation_id' => $record->id]);
                $changes = ['status' => 'local_preview', 'last_error_code' => null];
            } else {
                $record->load('registration');
                $sent = Mail::to($record->registration->email)->send(new RegistrationConfirmation($record->registration));
                $changes = ['status' => 'provider_accepted', 'provider_accepted_at' => now(), 'provider_message_id' => $sent?->getMessageId(), 'last_error_code' => null];
            }
            $logger->finish($logId, $changes['status'], messageId: $changes['provider_message_id'] ?? null);
            ConfirmationEmail::whereKey($record->id)->where('processing_token', $claim)->update([...$changes, 'lease_until' => null, 'processing_token' => null, 'next_attempt_at' => null]);
        } catch (Throwable $exception) {
            $logger->finish($logId, 'failed', $logger->error($exception));
            $failed = $record->attempts >= 5;
            ConfirmationEmail::whereKey($record->id)->where('processing_token', $claim)->update([
                'status' => $failed ? 'failed' : 'pending', 'last_error_code' => 'confirmation_transport_error',
                'failed_at' => $failed ? now() : null, 'next_attempt_at' => $failed ? null : now()->addSeconds(min(3600, 60 * (2 ** ($record->attempts - 1)))),
                'lease_until' => null, 'processing_token' => null,
            ]);
            Log::warning('Registration confirmation attempt failed.', ['confirmation_id' => $record->id, 'attempt' => $record->attempts]);
        }
    }
}
