<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as frameworkRefreshDatabase; }

    public function refreshDatabase(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertMatchesRegularExpression('/_test(?:ing)?$/', config('database.connections.mysql.database'));
        $this->frameworkRefreshDatabase();
    }

    public function test_seeded_admin_can_login_and_reseeding_preserves_existing_credentials_and_status(): void
    {
        $this->withoutVite();
        config(['admin-seeding.name' => 'Seed Administrator', 'admin-seeding.email' => ' SEED@example.test ', 'admin-seeding.password' => 'Synthetic!Seed123']);
        $this->seed(AdminUserSeeder::class);
        $admin = AdminUser::where('email', 'seed@example.test')->sole();
        $this->assertTrue(Hash::check('Synthetic!Seed123', $admin->password));
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Synthetic!Seed123'])->assertRedirect('/admin');
        $hash = $admin->password;
        $admin->update(['is_active' => false]);
        config(['admin-seeding.password' => 'Different!Seed123', 'admin-seeding.name' => 'Changed name']);
        $this->seed(AdminUserSeeder::class);
        $this->assertSame(1, AdminUser::count());
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertSame('Seed Administrator', $admin->fresh()->name);
        $this->assertFalse($admin->fresh()->is_active);
    }

    public function test_missing_credentials_skip_and_weak_password_does_not_create_an_account(): void
    {
        config(['admin-seeding.email' => null, 'admin-seeding.password' => null]);
        $this->seed(AdminUserSeeder::class);
        $this->assertSame(0, AdminUser::count());
        config(['admin-seeding.name' => 'Administrator', 'admin-seeding.email' => 'seed@example.test', 'admin-seeding.password' => 'weak']);
        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('A weak password must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
        $this->assertSame(0, AdminUser::count());
    }
}
