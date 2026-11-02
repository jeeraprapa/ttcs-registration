<?php

namespace Tests\Feature;

use App\Actions\RegisterForSession;
use App\Models\AdminUser;
use App\Models\Registration;
use App\Support\RegistrationDomains;
use App\Support\RegistrationQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckInScannerTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    private function signIn(): void
    {
        $admin = AdminUser::create(['name' => 'Scanner Staff', 'email' => 'scanner@example.test', 'password' => 'Synthetic!Pass123']);
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
    }

    private function booking(): Registration
    {
        $this->seed();
        $session = app(RegistrationDomains::class)->sessions('')->firstOrFail();

        return app(RegisterForSession::class)->execute([
            'idempotency_token' => str_repeat('s', 64), 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 2,
            'title' => 'Mr.', 'first_name' => 'Scanner', 'last_name' => 'Test',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'scanner-guest@example.test', 'referral_source' => 'Others',
            'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
        ]);
    }

    public function test_scanner_and_resolver_require_admin_login(): void
    {
        $this->get(route('admin.check-ins.scan'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.check-ins.resolve'), ['qr' => 'anything'])->assertUnauthorized();
    }

    public function test_scanner_has_camera_image_and_manual_controls(): void
    {
        $this->signIn();
        $this->get(route('admin.check-ins.scan'))->assertOk()->assertSee('playsinline', false)
            ->assertSee('data-qr-image', false)->assertSee('data-qr-input', false)
            ->assertSee(route('admin.check-ins.resolve'), false)->assertSee('Scan QR Check-in');
    }

    public function test_resolving_a_valid_qr_opens_details_without_checking_in(): void
    {
        $booking = $this->booking();
        $this->signIn();
        $this->postJson(route('admin.check-ins.resolve'), ['qr' => app(RegistrationQrCode::class)->url($booking)])
            ->assertOk()->assertJsonPath('url', app(RegistrationQrCode::class)->url($booking));
        $this->get(app(RegistrationQrCode::class)->url($booking))->assertOk()->assertSee('Confirm check-in')->assertSee('Scan next QR');
        $this->assertNull($booking->fresh()->checked_in_at);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'registration.check_in']);
    }

    public function test_tampered_expired_unsigned_and_unrelated_qr_links_are_rejected(): void
    {
        $booking = $this->booking();
        $this->signIn();
        $valid = app(RegistrationQrCode::class)->url($booking);
        $invalid = [
            '', 'not a URL', 'https://example.test/',
            route('admin.check-ins.show', $booking->reference),
            $valid.'tampered',
            preg_replace('/signature=[^&]+/', 'signature[]=bad', $valid),
            URL::temporarySignedRoute('admin.check-ins.show', now()->subMinute(), ['registration' => $booking->reference]),
            URL::signedRoute('admin.registrations.show', $booking),
        ];
        foreach ($invalid as $qr) {
            $this->postJson(route('admin.check-ins.resolve'), ['qr' => $qr])->assertUnprocessable()->assertJsonValidationErrors('qr');
        }
        $this->assertNull($booking->fresh()->checked_in_at);
    }

    public function test_qr_from_another_registration_domain_is_resolved_on_the_current_domain(): void
    {
        $booking = $this->booking();
        $this->signIn();
        $root = config('app.url');
        try {
            URL::forceRootUrl('https://registration.uhhk.uhresidencesbangkok.com');
            $qr = app(RegistrationQrCode::class)->url($booking);
        } finally {
            URL::forceRootUrl($root);
        }
        $this->postJson(route('admin.check-ins.resolve'), ['qr' => $qr])->assertOk()
            ->assertJsonPath('url', app(RegistrationQrCode::class)->url($booking));
    }

    public function test_cancelled_and_checked_in_bookings_keep_their_status_when_scanned(): void
    {
        $booking = $this->booking();
        $this->signIn();
        $booking->update(['checked_in_at' => '2026-11-01 09:37:00']);
        $checkedInTime = $booking->fresh()->checked_in_at->timezone($booking->timezone_snapshot)->format('j M Y H:i');
        $url = app(RegistrationQrCode::class)->url($booking);
        $this->postJson(route('admin.check-ins.resolve'), ['qr' => $url])->assertOk();
        $this->get($url)->assertOk()->assertSee('Already checked in:')->assertDontSee('Confirm check-in');
        $this->get(route('admin.registrations.index'))->assertOk()->assertSee($checkedInTime);
        $booking->update(['cancelled_at' => now(), 'active_session_id' => null]);
        $this->postJson(route('admin.check-ins.resolve'), ['qr' => $url])->assertOk();
        $this->get($url)->assertOk()->assertSee('admission unavailable')->assertDontSee('Confirm check-in');
        $this->get(route('admin.registrations.index'))->assertOk()->assertSee('Cancelled')->assertDontSee($checkedInTime);
        $this->get(route('admin.registrations.show', $booking))->assertOk()->assertSee('Cancelled')->assertDontSee('Checked in:')->assertDontSee($checkedInTime);
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }

    public function test_browser_decoder_reads_the_registration_png_qr(): void
    {
        $booking = $this->booking();
        $qr = app(RegistrationQrCode::class);
        $image = imagecreatefromstring($qr->png($booking));
        $pixels = '';
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $pixels .= pack('C4', $color['red'], $color['green'], $color['blue'], 255);
            }
        }
        $process = new Process(['node', '-e', <<<'JS'
const fs = require('fs');
const qr = require('jsqr');
const image = JSON.parse(fs.readFileSync(0, 'utf8'));
const result = qr(new Uint8ClampedArray(Buffer.from(image.pixels, 'base64')), image.width, image.height);
if (!result) process.exit(1);
process.stdout.write(result.data);
JS
        ], base_path());
        $process->setInput(json_encode(['pixels' => base64_encode($pixels), 'width' => imagesx($image), 'height' => imagesy($image)], JSON_THROW_ON_ERROR));
        $process->mustRun();
        $this->assertSame($qr->url($booking), $process->getOutput());
    }
}
