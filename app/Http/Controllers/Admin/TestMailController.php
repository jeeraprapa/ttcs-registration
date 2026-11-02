<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MailDeliveryLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TestMailController extends Controller
{
    public function create(): View
    {
        Gate::authorize('manage-admin');

        return view('admin.test-mail', ['mailSettings' => [
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme') ?: 'smtp',
            'username' => config('mail.mailers.smtp.username'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ], 'hasSavedPassword' => filled(config('mail.mailers.smtp.password'))]);
    }

    public function store(Request $request, MailDeliveryLogger $logger): RedirectResponse
    {
        Gate::authorize('manage-admin');
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.\\:\\[\\]-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'username' => ['required', 'string', 'max:254', 'not_regex:/[\\r\\n]/'],
            'password' => ['nullable', 'string', 'max:4096', Rule::requiredIf(
                fn (): bool => strcasecmp((string) $request->input('host'), (string) config('mail.mailers.smtp.host')) !== 0
                    || $request->input('username') !== config('mail.mailers.smtp.username')
                    || blank(config('mail.mailers.smtp.password'))
            )],
            'from_address' => ['required', 'string', 'email:rfc', 'max:254'],
            'from_name' => ['nullable', 'string', 'max:150', 'not_regex:/[\\r\\n]/'],
        ]);
        $password = $data['password'] ?? config('mail.mailers.smtp.password');
        $safeInput = $request->except(['password', '_token']);

        $logId = $logger->start([
            'type' => 'test', 'recipient' => $data['email'], 'from_address' => $data['from_address'],
            'subject' => '[TEST] Upper House registration - admin email test',
            'mailer' => 'smtp', 'smtp_host' => $data['host'], 'smtp_port' => (int) $data['port'],
            'admin_user_id' => $request->user('admin')->id,
        ]);
        try {
            $mailer = Mail::build([
                'transport' => 'smtp', 'host' => $data['host'], 'port' => (int) $data['port'],
                'scheme' => $data['scheme'], 'username' => $data['username'], 'password' => $password,
                'timeout' => 20, 'require_tls' => true,
            ]);
            $sent = $mailer->raw('This is a test email from the Upper House Residences registration admin. No booking has been created.', function (Message $message) use ($data): void {
                $message->from($data['from_address'], $data['from_name'] ?? null)->to($data['email'])->subject('[TEST] Upper House registration - admin email test');
            });
        } catch (Throwable $exception) {
            $error = $logger->error($exception, [$password]);
            $logger->finish($logId, 'failed', $error);

            return redirect()->route('admin.send-testmail')->withInput($safeInput)->withErrors([
                'mail' => 'Email could not be sent: '.mb_substr($error, 0, 2000),
            ]);
        }

        $logger->finish($logId, 'provider_accepted', messageId: $sent?->getMessageId());

        return redirect()->route('admin.send-testmail')->withInput($safeInput)->with('status', 'Test email accepted by the configured mail transport. Check the recipient inbox and spam folder; inbox delivery is not yet confirmed.');
    }
}
