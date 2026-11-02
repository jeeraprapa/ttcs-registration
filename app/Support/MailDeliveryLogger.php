<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailDeliveryLogger
{
    public const array STATUSES = [
        'processing' => 'Sending', 'provider_accepted' => 'Accepted by mail server',
        'failed' => 'Failed', 'local_preview' => 'Preview only', 'unknown' => 'Outcome unknown',
    ];

    /**
     * @param  array{type: string, recipient: string, subject: string, confirmation_email_id?: int, admin_user_id?: int, from_address?: ?string, mailer?: ?string, smtp_host?: ?string, smtp_port?: ?int, attempt?: int}  $data
     */
    public function start(array $data): int
    {
        return DB::table('mail_delivery_logs')->insertGetId([
            ...$data, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function finish(int $id, string $status, ?string $error = null, ?string $messageId = null): void
    {
        try {
            DB::table('mail_delivery_logs')->where('id', $id)->where('status', 'processing')->update([
                'status' => $status, 'error_message' => $error,
                'provider_message_id' => $messageId === null ? null : mb_substr($messageId, 0, 255),
                'finished_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Mail delivery history update failed.', ['mail_log_id' => $id]);
        }
    }

    /** @param list<string|null> $additionalSecrets */
    public function error(Throwable $exception, array $additionalSecrets = []): string
    {
        $secrets = array_filter([
            ...$additionalSecrets, config('mail.mailers.smtp.password'), config('mail.mailers.smtp.url'),
        ], fn ($value): bool => is_string($value) && $value !== '');
        $redactions = [];
        foreach ($secrets as $secret) {
            $redactions = [...$redactions, $secret, rawurlencode($secret), base64_encode($secret)];
        }

        return mb_substr(str_replace($redactions, '[redacted]', $exception->getMessage()), 0, 4000);
    }
}
