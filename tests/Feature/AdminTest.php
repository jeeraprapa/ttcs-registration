<?php

namespace Tests\Feature;

use App\Actions\RegisterForSession;
use App\Models\AdminUser;
use App\Models\EventSession;
use App\Support\RegistrationDomains;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    private function admin(): AdminUser
    {
        return AdminUser::create(['name' => 'Test Admin', 'email' => 'admin@example.com', 'password' => 'Synthetic!Pass123']);
    }

    public function test_dashboard_view_handles_missing_url_summary(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->view('admin.dashboard', [
            'registrationCount' => 0, 'guestCount' => 0, 'sessions' => collect(), 'errors' => new ViewErrorBag,
        ])->assertSee('Event overview')
            ->assertSee('Registration URL summary is currently unavailable.')
            ->assertSee('Registration by session');
    }

    public function test_all_admin_pages_require_login_and_no_public_admin_signup_exists(): void
    {
        foreach (['/admin', '/admin/registrations', '/admin/registrations/export', '/admin/registrations/1', '/admin/sessions', '/admin/sessions/1/edit'] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        $this->patch('/admin/sessions/1', ['is_open' => 0])->assertRedirect('/admin/login');
        $this->get('/admin/register')->assertNotFound();
        $this->get('/admin/logout')->assertStatus(405);
    }

    public function test_admin_login_logout_and_wrong_password(): void
    {
        $admin = $this->admin();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Synthetic!Pass123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_disabled_admin_and_password_version_revoke_session(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $admin->update(['is_active' => false]);
        $this->get('/admin')->assertRedirect('/admin/login');
        $admin->update(['is_active' => true, 'password_version' => 2]);
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_can_close_session_and_change_is_audited_but_capacity_is_unlimited(): void
    {
        $this->seed();
        $admin = $this->admin();
        $session = EventSession::firstOrFail();
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $this->patch('/admin/sessions/'.$session->id, ['is_open' => 0])->assertRedirect('/admin/sessions');
        $this->assertFalse($session->fresh()->is_open);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_user_id' => $admin->id, 'action' => 'session.update']);
        $this->patch('/admin/sessions/'.$session->id, ['is_open' => 1, 'capacity' => 10])->assertSessionHasErrors('capacity');
    }

    public function test_admin_command_requires_password_and_hashes_it(): void
    {
        $this->artisan('admin:create', ['email' => 'command@example.com', '--name' => 'Command Admin'])
            ->expectsQuestion('Password (at least 12 characters)', 'Synthetic!Pass123')
            ->expectsQuestion('Confirm password', 'Synthetic!Pass123')
            ->assertExitCode(0);
        $user = AdminUser::where('email', 'command@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic!Pass123', $user->password));
        $this->assertNotSame('Synthetic!Pass123', $user->password);
    }

    public function test_admin_login_is_throttled_and_logout_requires_csrf(): void
    {
        $admin = $this->admin();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertStatus(429);
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $this->app['env'] = 'local';
        $this->post('/admin/logout')->assertStatus(419);
    }

    public function test_password_reset_command_revokes_sessions(): void
    {
        $admin = $this->admin();
        $this->artisan('admin:password', ['email' => $admin->email])
            ->expectsQuestion('New password', 'Another!Pass123')
            ->expectsQuestion('Confirm password', 'Another!Pass123')
            ->assertExitCode(0);
        $this->assertSame(2, $admin->fresh()->password_version);
        $this->assertTrue(Hash::check('Another!Pass123', $admin->fresh()->password));
    }

    public function test_date_filters_accept_end_only_and_use_event_timezone_boundaries(): void
    {
        $this->seed();
        $session = EventSession::firstOrFail();
        $booking = app(RegisterForSession::class)->execute([
            'idempotency_token' => str_repeat('d', 64), 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 1,
            'title' => 'Mr.', 'first_name' => 'Boundary', 'last_name' => 'Test',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'boundary@example.com', 'referral_source' => 'Others', 'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
        ]);
        $booking->update(['created_at' => '2026-10-09 17:00:00']); // 10 October, 01:00 in Hong Kong.
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        $this->get('/admin/registrations?registered_to=2026-10-09')->assertOk()
            ->assertViewHas('registrations', fn ($list) => $list->total() === 0);
        $this->get('/admin/registrations?registered_from=2026-10-10&registered_to=2026-10-10')->assertOk()
            ->assertViewHas('registrations', fn ($list) => $list->total() === 1);
        $this->get('/admin/registrations?registered_from=2026-10-11&registered_to=2026-10-10')->assertSessionHasErrors('registered_to');
    }

    public function test_registration_url_filter_applies_to_list_and_export(): void
    {
        $this->seed();
        $session = EventSession::firstOrFail();
        config(['registration.registration_urls' => null]);
        $urls = RegistrationDomains::REGISTRATION_URLS;
        $bookings = [];
        foreach ([...$urls, null] as $index => $url) {
            $session = EventSession::orderBy('id')->skip($index)->firstOrFail();
            $bookings[] = app(RegisterForSession::class)->execute([
                'idempotency_token' => hash('sha256', 'url-filter-'.$index),
                'event_session_id' => $session->id, 'selected_date' => $session->local_date->format('Y-m-d'),
                'guest_count' => 1, 'title' => 'Mr.', 'first_name' => 'URL', 'last_name' => 'Filter',
                'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '00000000',
                'email' => 'url'.$index.'@example.test', 'referral_source' => 'Others',
                'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
                'registration_url' => $index === 1 ? rtrim($url, '/') : $url,
            ]);
        }
        $this->actingAs($this->admin(), 'admin')->withSession(['admin_password_version' => 1]);
        $this->get('/admin/registrations')->assertOk()
            ->assertSee('All registration URLs')
            ->assertViewHas('registrations', fn ($list) => $list->total() === 4);
        $this->get('/admin')->assertOk()->assertSee('Registrations by URL')
            ->assertViewHas('registrationsByUrl', fn ($sources) => $sources->count() === 3
                && $sources->pluck('url')->all() === $urls
                && $sources->every(fn ($source) => $source['bookings'] === 1 && $source['guests'] === 1));

        foreach ($urls as $index => $url) {
            $query = http_build_query(['registration_url' => $url, 'referral_source' => 'Others']);
            $this->get('/admin/registrations?'.$query)->assertOk()
                ->assertViewHas('registrations', fn ($list) => $list->total() === 1 && $list->first()->id === $bookings[$index]->id);
            $response = $this->get('/admin/registrations/export?'.$query)->assertOk()->assertDownload();
            $path = tempnam(sys_get_temp_dir(), 'url-export-');
            $reader = new Reader;
            try {
                file_put_contents($path, $response->streamedContent());
                $reader->open($path);
                $rows = [];
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $rows[] = $row->toArray();
                    }
                }
                $this->assertCount(2, $rows);
                $this->assertSame($bookings[$index]->reference, $rows[1][0]);
            } finally {
                $reader->close();
                unlink($path);
            }
        }
        foreach (['/admin/registrations', '/admin/registrations/export'] as $path) {
            $this->get($path.'?registration_url=https://unconfigured.example/')->assertSessionHasErrors('registration_url');
        }
    }

    public function test_excel_export_includes_all_filtered_rows_and_preserves_text_safely(): void
    {
        $this->seed();
        $session = EventSession::firstOrFail();
        for ($i = 0; $i < 22; $i++) {
            $session = EventSession::orderBy('id')->skip($i)->firstOrFail();
            app(RegisterForSession::class)->execute([
                'idempotency_token' => hash('sha256', 'export-'.$i), 'event_session_id' => $session->id,
                'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 2,
                'title' => 'Mr.', 'first_name' => $i === 21 ? 'Excluded' : '=1+1', 'last_name' => 'Export',
                'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '05551234',
                'email' => 'export'.$i.'@example.test', 'referral_source' => 'Others', 'consents' => ['privacy' => true, 'marketing_direct' => true, 'marketing_sharing' => true],
            ]);
        }
        $this->actingAs($this->admin(), 'admin')->withSession(['admin_password_version' => 1]);
        $this->get('/admin/registrations?q=%3D1%2B1&email_status=failed')->assertOk()
            ->assertSee('Export Excel (.xlsx)')
            ->assertDontSee('Email status')
            ->assertDontSee('name="email_status"', false)
            ->assertViewHas('registrations', fn ($list) => $list->total() === 21)
            ->assertViewHas('filters', fn ($filters) => ! array_key_exists('email_status', $filters));
        $response = $this->get('/admin/registrations/export?q=%3D1%2B1&page=2&email_status=failed');
        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'export-test-');
        try {
            file_put_contents($path, $response->streamedContent());
            $reader = new Reader;
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $reader->close();
            $this->assertCount(22, $rows); // Header plus 21 matches, beyond one page.
            $this->assertSame('=1+1', $rows[1][2]);
            $this->assertSame('05551234', $rows[1][7]);
            $this->assertSame('+852', $rows[1][6]);
            $this->assertSame($session->timezone, $rows[1][10]);
            $this->assertSame(2, $rows[1][11]);
            $this->assertNotContains('Email status', $rows[0]);
            $this->assertCount(18, $rows[0]);
            $this->assertCount(18, $rows[1]);
            $this->assertSame('Registered at ('.config('registration.timezone').')', $rows[0][13]);
            $this->assertSame('Registration URL', $rows[0][14]);
            $zip = new \ZipArchive;
            $zip->open($path);
            $this->assertStringNotContainsString('<f>', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $zip->close();
        } finally {
            unlink($path);
        }
        $this->get('/admin/registrations/export?session_id=bad')->assertSessionHasErrors('session_id');
    }
}
