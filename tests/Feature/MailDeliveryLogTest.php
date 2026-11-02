<?php

namespace Tests\Feature;

use App\Actions\RegisterForSession;
use App\Jobs\SendRegistrationConfirmation;
use App\Models\AdminUser;
use App\Models\ConfirmationEmail;
use App\Models\EventSession;
use App\Support\MailDeliveryLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class MailDeliveryLogTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    private function confirmation(): ConfirmationEmail
    {
        $this->seed();
        $session = EventSession::firstOrFail();
        $booking = app(RegisterForSession::class)->execute([
            'idempotency_token' => str_repeat('a', 64), 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 1,
            'title' => 'Mr.', 'first_name' => 'Mail', 'last_name' => 'Log',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'guest@example.test', 'referral_source' => 'Others',
            'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
        ]);

        return $booking->confirmationEmails()->firstOrFail();
    }

    private function signIn(): void
    {
        $admin = AdminUser::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Synthetic!Pass123']);
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
    }

    public function test_confirmation_success_and_duplicate_worker_produce_one_history_entry(): void
    {
        $record = $this->confirmation();
        Mail::fake();
        config(['registration.mail_mode' => 'send']);
        (new SendRegistrationConfirmation($record->id))->handle();
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertDatabaseCount('mail_delivery_logs', 1);
        $this->assertDatabaseHas('mail_delivery_logs', ['confirmation_email_id' => $record->id,
            'recipient' => 'guest@example.test', 'type' => 'confirmation', 'attempt' => 1, 'status' => 'provider_accepted']);
        $this->assertNotNull(DB::table('mail_delivery_logs')->first()->finished_at);
    }

    public function test_failed_attempt_is_preserved_when_retry_succeeds_and_secrets_are_redacted(): void
    {
        $record = $this->confirmation();
        config(['registration.mail_mode' => 'send', 'mail.mailers.smtp.password' => 'Secret@123']);
        $mailManager = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('535 rejected Secret@123 '.base64_encode('Secret@123')));
        (new SendRegistrationConfirmation($record->id))->handle();
        $log = DB::table('mail_delivery_logs')->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('535 rejected', $log->error_message);
        $this->assertStringNotContainsString('Secret@123', $log->error_message);
        $this->assertStringNotContainsString(base64_encode('Secret@123'), $log->error_message);
        $this->assertSame('pending', $record->fresh()->status);
        $record->update(['next_attempt_at' => now()->subSecond()]);
        Mail::swap($mailManager);
        Mail::fake();
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertDatabaseHas('mail_delivery_logs', ['attempt' => 1, 'status' => 'failed']);
        $this->assertDatabaseHas('mail_delivery_logs', ['attempt' => 2, 'status' => 'provider_accepted']);
        $this->assertDatabaseCount('mail_delivery_logs', 2);
    }

    public function test_local_preview_is_logged_without_claiming_real_delivery(): void
    {
        $record = $this->confirmation();
        Mail::fake();
        config(['registration.mail_mode' => 'local']);
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertDatabaseHas('mail_delivery_logs', ['status' => 'local_preview']);
        Mail::assertNothingSent();
    }

    public function test_expired_worker_history_becomes_unknown_when_a_new_worker_claims_it(): void
    {
        $record = $this->confirmation();
        $record->update(['status' => 'processing', 'attempts' => 1, 'lease_until' => now()->subMinute()]);
        app(MailDeliveryLogger::class)->start(['confirmation_email_id' => $record->id, 'type' => 'confirmation', 'recipient' => 'guest@example.test', 'subject' => 'Confirmation']);
        Mail::fake();
        config(['registration.mail_mode' => 'send']);
        (new SendRegistrationConfirmation($record->id))->handle();
        $this->assertDatabaseHas('mail_delivery_logs', ['attempt' => 1, 'status' => 'unknown']);
        $this->assertDatabaseHas('mail_delivery_logs', ['attempt' => 2, 'status' => 'provider_accepted']);
    }

    public function test_history_requires_active_admin_and_supports_filters_pagination_and_escaped_errors(): void
    {
        $this->get(route('admin.mail-logs.index'))->assertRedirect(route('admin.login'));
        $this->signIn();
        $logger = app(MailDeliveryLogger::class);
        $first = $logger->start(['type' => 'test', 'recipient' => 'first@example.test', 'subject' => 'Test']);
        $logger->finish($first, 'failed', '<script>alert(1)</script>');
        for ($index = 0; $index < 21; $index++) {
            $id = $logger->start(['type' => 'confirmation', 'recipient' => 'second@example.test', 'subject' => 'Confirmation']);
            $logger->finish($id, 'provider_accepted');
        }
        $this->get(route('admin.mail-logs.index'))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 22 && $logs->count() === 20);
        $this->get(route('admin.mail-logs.index', ['recipient' => 'first@example.test', 'type' => 'test', 'status' => 'failed']))
            ->assertOk()->assertSee('first@example.test')->assertDontSee('second@example.test')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
        $this->get(route('admin.mail-logs.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
        auth('admin')->user()->update(['is_active' => false]);
        $this->get(route('admin.mail-logs.index'))->assertRedirect(route('admin.login'));
    }
}
