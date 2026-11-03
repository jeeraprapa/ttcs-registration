<?php

namespace Tests\Feature;

use App\Actions\RegisterForSession;
use App\Jobs\SendRegistrationConfirmation;
use App\Mail\RegistrationConfirmation;
use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use App\Models\Registration;
use App\Support\EventSchedule;
use App\Support\RegistrationDomains;
use App\Support\RegistrationQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Queue::fake();
        Mail::fake();
    }

    private function payload(string $token = 'a'): array
    {
        $session = app(RegistrationDomains::class)->sessions('')->firstOrFail();

        return [
            'idempotency_token' => str_repeat($token, 64), 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 4,
            'title' => 'Mr.', 'first_name' => 'Booking', 'last_name' => 'Test',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'booking@example.test', 'referral_source' => 'Others',
            'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
        ];
    }

    private function admin(): AdminUser
    {
        return AdminUser::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Synthetic!Pass123']);
    }

    public function test_schedule_has_exact_dates_and_seven_times_on_every_host(): void
    {
        foreach (RegistrationDomains::REGISTRATION_URLS as $url) {
            $this->get($url)->assertOk()->assertViewHas('sessions', function ($sessions): bool {
                $this->assertCount(112, $sessions);
                $this->assertSame(EventSchedule::dates(), $sessions->pluck('local_date')->map(fn ($date) => $date->format('Y-m-d'))->unique()->values()->all());
                foreach ($sessions->groupBy(fn ($session) => $session->local_date->format('Y-m-d')) as $day) {
                    $this->assertSame(EventSchedule::TIMES, $day->pluck('local_start_time')->all());
                }

                return true;
            });
        }
    }

    public function test_slot_accepts_one_booking_and_shows_it_disabled(): void
    {
        $booking = app(RegisterForSession::class)->execute($this->payload());
        $this->assertSame($booking->event_session_id, $booking->active_session_id);
        $this->getJson(route('registration.availability'))->assertOk()->assertJsonFragment(['id' => $booking->event_session_id, 'available' => false, 'status' => 'Booked'])->assertJsonMissingPath('0.email');
        $this->get('/')->assertOk()->assertViewHas('sessions', fn ($sessions) => (bool) $sessions->firstWhere('id', $booking->event_session_id)->is_booked)->assertSee('— Booked');
        try {
            app(RegisterForSession::class)->execute($this->payload('b'));
            $this->fail('A second booking must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('event_session_id', $exception->errors());
        }
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('confirmation_emails', 1);
    }

    public function test_admin_cancellation_is_idempotent_releases_slot_and_blocks_old_qr(): void
    {
        $booking = app(RegisterForSession::class)->execute($this->payload());
        $this->actingAs($this->admin(), 'admin')->withSession(['admin_password_version' => 1]);
        $this->post(route('admin.registrations.cancel', $booking))->assertRedirect();
        $this->post(route('admin.registrations.cancel', $booking))->assertRedirect();
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertNull($booking->fresh()->active_session_id);
        $this->getJson(route('registration.availability'))->assertOk()->assertJsonFragment(['id' => $booking->event_session_id, 'available' => true, 'status' => 'Available']);
        $this->assertSame(0, $booking->eventSession->fresh()->reserved_seats);
        $this->assertSame(1, AdminAuditLog::where('action', 'registration.cancel')->count());
        $this->assertSame('cancelled', $booking->confirmationEmails()->first()->status);
        $url = URL::signedRoute('admin.check-ins.store', ['registration' => $booking->reference]);
        $this->post($url)->assertSessionHasErrors('registration');
        $this->assertNull($booking->fresh()->checked_in_at);
        $replacement = app(RegisterForSession::class)->execute($this->payload('b'));
        $this->assertSame($booking->event_session_id, $replacement->event_session_id);
        $this->assertDatabaseCount('registrations', 2);
        try {
            app(RegisterForSession::class)->execute($this->payload());
            $this->fail('Cancelled requests cannot resurrect a booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_token', $exception->errors());
        }
    }

    public function test_check_in_requires_login_valid_signature_and_explicit_confirmation(): void
    {
        $booking = app(RegisterForSession::class)->execute($this->payload());
        $url = app(RegistrationQrCode::class)->url($booking);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->post(route('admin.registrations.cancel', $booking))->assertRedirect(route('admin.login'));
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->get($url)->assertOk()->assertSee('Confirm check-in');
        $this->assertNull($booking->fresh()->checked_in_at);
        $postUrl = URL::signedRoute('admin.check-ins.store', ['registration' => $booking->reference]);
        $this->post($postUrl)->assertRedirect();
        $checkedInAt = $booking->fresh()->checked_in_at;
        $this->post($postUrl)->assertRedirect();
        $this->assertTrue($checkedInAt->equalTo($booking->fresh()->checked_in_at));
        $this->assertSame($admin->id, $booking->fresh()->checked_in_by);
        $this->assertSame(1, AdminAuditLog::where('action', 'registration.check_in')->count());
        $this->get($url)->assertOk()->assertSee('Already checked in');
        $this->get(route('admin.registrations.index', ['attendance' => 'checked_in']))->assertOk()->assertSee($booking->email);
        $this->get(route('admin.registrations.index', ['attendance' => 'booked']))->assertOk()->assertDontSee($booking->email);
    }

    public function test_thank_you_and_mail_contain_qr_and_mail_sends_once(): void
    {
        $data = $this->payload();
        $this->withSession(['registration_tokens' => [hash('sha256', $data['idempotency_token']) => time() + 3600]])
            ->post(route('registration.store'), $data)->assertRedirect(route('registration.thank-you'));
        $booking = Registration::firstOrFail();
        Queue::assertPushed(SendRegistrationConfirmation::class, fn ($job) => $job->confirmationEmailId === $booking->confirmationEmails()->first()->id);
        $this->get(route('registration.thank-you'))->assertOk()->assertSee('data:image/png;base64,', false)->assertSee($booking->reference);
        $qr = app(RegistrationQrCode::class)->png($booking);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($qr, 0, 8));
        $mail = new RegistrationConfirmation($booking);
        $mail->assertSeeInHtml('THANK YOU FOR REGISTERING');
        $mail->assertSeeInHtml('The Tong Chong Suite, 9/F Cambridge House');
        $mail->assertSeeInHtml("979 King's Road, Taikoo Place, Quarry Bay, Hong Kong");
        $mail->assertSeeInHtml('data:image/png;base64,');
        $sent = (new MailManager($this->app))->mailer('array')->to($booking->email)->send(new RegistrationConfirmation($booking));
        $mime = $sent->getOriginalMessage();
        $this->assertStringContainsString('cid:', $mime->getHtmlBody());
        $this->assertNotEmpty($mime->getAttachments());
        $mail->assertSeeInHtml($booking->reference);
        config(['registration.mail_mode' => 'send']);
        $record = $booking->confirmationEmails()->first();
        (new SendRegistrationConfirmation($record->id))->handle();
        (new SendRegistrationConfirmation($record->id))->handle();
        Mail::assertSent(RegistrationConfirmation::class, 1);
        $this->assertSame('provider_accepted', $record->fresh()->status);
    }

    public function test_cancelled_pending_booking_never_sends_confirmation_and_closed_session_stays_closed(): void
    {
        $booking = app(RegisterForSession::class)->execute($this->payload());
        $booking->eventSession->update(['is_open' => false]);
        $this->actingAs($this->admin(), 'admin')->withSession(['admin_password_version' => 1])->post(route('admin.registrations.cancel', $booking))->assertRedirect();
        config(['registration.mail_mode' => 'send']);
        (new SendRegistrationConfirmation($booking->confirmationEmails()->first()->id))->handle();
        Mail::assertNothingSent();
        $this->assertFalse($booking->eventSession->fresh()->is_open);
    }
}
