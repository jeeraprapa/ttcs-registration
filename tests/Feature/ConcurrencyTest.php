<?php

namespace Tests\Feature;

use App\Models\EventSession;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as frameworkRunDatabaseMigrations; }

    public function runDatabaseMigrations(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRunDatabaseMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_simultaneous_same_token_creates_one_booking_and_one_email(): void
    {
        $this->seed();
        $session = EventSession::firstOrFail();
        $data = [
            'idempotency_token' => str_repeat('c', 64), 'event_session_id' => $session->id,
            'selected_date' => $session->local_date->format('Y-m-d'), 'guest_count' => 2,
            'title' => 'Mr.', 'first_name' => 'Concurrent', 'last_name' => 'Test',
            'phone_country_code' => 'HK', 'phone_dial_code' => '+852', 'phone_number' => '55551234',
            'email' => 'concurrent@example.com', 'referral_source' => 'Others', 'consents' => ['privacy' => true],
        ];
        $command = [PHP_BINARY, base_path('tests/Fixtures/booking-worker.php'), json_encode($data, JSON_THROW_ON_ERROR)];
        $first = new Process($command, base_path(), ['APP_ENV' => 'testing']);
        $second = new Process($command, base_path(), ['APP_ENV' => 'testing']);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $this->assertSame($first->getOutput(), $second->getOutput());
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('confirmation_emails', 1);
        $this->assertDatabaseCount('registration_consents', 4);
        $this->assertSame(2, $session->fresh()->reserved_seats);
    }
}
