<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class AdminTestMailTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    private function signIn(bool $active = true): void
    {
        $admin = AdminUser::create(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => 'Synthetic!Pass123', 'is_active' => $active]);
        $this->actingAs($admin, 'admin')->withSession(['admin_password_version' => 1]);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'Env-Secret',
            'mail.from.address' => 'sender@example.test', 'mail.from.name' => 'Test Sender']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'email' => 'recipient@example.test', 'host' => 'smtp.example.test', 'port' => 587,
            'scheme' => 'smtp', 'username' => 'sender@example.test',
            'from_address' => 'sender@example.test', 'from_name' => 'Test Sender',
        ], $overrides);
    }

    private function mailer(int $times = 1): Mailer
    {
        $mailer = Mockery::mock(Mailer::class);
        Mail::shouldReceive('build')->times($times)->with(Mockery::on(function (array $config): bool {
            $this->assertSame('smtp', $config['transport']);
            $this->assertTrue($config['require_tls']);

            return true;
        }))->andReturn($mailer);

        return $mailer;
    }

    public function test_guests_and_inactive_admins_cannot_access_or_send_test_mail(): void
    {
        Mail::shouldReceive('build')->never();
        $this->get('/admin/send-testmail')->assertRedirect(route('admin.login'));
        $this->post('/admin/send-testmail', ['email' => 'test@example.test'])->assertRedirect(route('admin.login'));
        $this->signIn(false);
        $this->post('/admin/send-testmail', ['email' => 'test@example.test'])->assertRedirect(route('admin.login'));
    }

    public function test_page_uses_current_config_without_exposing_password_or_sending_mail(): void
    {
        $this->signIn();
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.password' => 'Private-Smtp-Secret']);
        Mail::shouldReceive('build')->never();
        $this->get('/admin/send-testmail')->assertOk()->assertSee('smtp.example.test')
            ->assertSee('Send test email')->assertDontSee('Private-Smtp-Secret')->assertSee('name="host"', false)->assertSee('value="sender@example.test"', false);
    }

    public function test_sends_one_message_to_validated_recipient_without_creating_a_booking(): void
    {
        $this->signIn();
        $this->mailer()->shouldReceive('raw')->once()->withArgs(function (string $body, Closure $callback): bool {
            $email = new Email;
            $callback(new Message($email));
            $this->assertSame('recipient@example.test', $email->getTo()[0]->getAddress());
            $this->assertStringContainsString('No booking has been created', $body);
            $this->assertStringContainsString('[TEST]', $email->getSubject());

            return true;
        });
        $this->post('/admin/send-testmail', $this->payload())->assertRedirect(route('admin.send-testmail'))
            ->assertSessionHas('status')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_invalid_recipient_is_rejected_before_sending(): void
    {
        $this->signIn();
        Mail::shouldReceive('build')->never();
        $this->post('/admin/send-testmail', $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
    }

    public function test_transport_error_is_reported_with_secrets_redacted(): void
    {
        $this->signIn();
        $secret = 'Private@Secret123';
        config(['mail.mailers.smtp.password' => $secret]);
        $this->mailer()->shouldReceive('raw')->once()->andThrow(new RuntimeException('535 Authentication failed '.$secret.' '.rawurlencode($secret).' '.base64_encode($secret)));
        $this->post('/admin/send-testmail', $this->payload())->assertRedirect(route('admin.send-testmail'))->assertSessionHasErrors('mail');
        $this->assertNull(session('_old_input.password'));
        $error = session('errors')->first('mail');
        $this->assertStringContainsString('535 Authentication failed', $error);
        $this->assertStringContainsString('[redacted]', $error);
        foreach ([$secret, rawurlencode($secret), base64_encode($secret)] as $value) {
            $this->assertStringNotContainsString($value, $error);
        }
    }

    public function test_custom_configuration_is_used_without_modifying_global_config(): void
    {
        $this->signIn();
        config(['mail.default' => 'log']);
        $mailer = Mockery::mock(Mailer::class);
        Mail::shouldReceive('build')->once()->with(Mockery::on(function (array $config): bool {
            return $config['host'] === 'other.example.test' && $config['port'] === 465
                && $config['scheme'] === 'smtps' && $config['username'] === 'other@example.test'
                && $config['password'] === 'Submitted-Secret';
        }))->andReturn($mailer);
        $mailer->shouldReceive('raw')->once()->withArgs(function (string $body, Closure $callback): bool {
            $email = new Email;
            $callback(new Message($email));
            $this->assertSame('other@example.test', $email->getFrom()[0]->getAddress());

            return true;
        });
        $this->post('/admin/send-testmail', $this->payload([
            'host' => 'other.example.test', 'port' => 465, 'scheme' => 'smtps',
            'username' => 'other@example.test', 'password' => 'Submitted-Secret', 'from_address' => 'other@example.test',
        ]))->assertSessionHasNoErrors()->assertSessionHas('status')->assertSessionMissing('_old_input.password');
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame('Env-Secret', config('mail.mailers.smtp.password'));
        $this->assertSame('log', config('mail.default'));
    }

    public function test_saved_password_is_used_when_left_blank(): void
    {
        $this->signIn();
        $mailer = Mockery::mock(Mailer::class);
        Mail::shouldReceive('build')->once()->with(Mockery::on(fn (array $config): bool => $config['password'] === 'Env-Secret'))->andReturn($mailer);
        $mailer->shouldReceive('raw')->once();
        $this->post('/admin/send-testmail', $this->payload(['password' => '']))->assertSessionHasNoErrors();
    }

    public function test_changed_host_requires_a_password_and_validation_never_flashes_it(): void
    {
        $this->signIn();
        Mail::shouldReceive('build')->never();
        $this->post('/admin/send-testmail', $this->payload(['host' => 'other.example.test']))->assertSessionHasErrors('password');
        $this->post('/admin/send-testmail', $this->payload(['port' => 70000, 'password' => 'Submitted-Secret']))
            ->assertSessionHasErrors('port')->assertSessionMissing('_old_input.password');
    }

    public function test_send_is_rate_limited(): void
    {
        $this->signIn();
        $this->mailer(3)->shouldReceive('raw')->times(3);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/admin/send-testmail', $this->payload())->assertRedirect();
        }
        $this->post('/admin/send-testmail', $this->payload())->assertStatus(429);
    }

    public function test_send_requires_csrf(): void
    {
        $this->signIn();
        Mail::shouldReceive('build')->never();
        $this->app['env'] = 'local';
        $this->post('/admin/send-testmail', $this->payload())->assertStatus(419);
    }
}
