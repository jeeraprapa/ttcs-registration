<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('admin-seeding.email')));
        $password = config('admin-seeding.password');

        if ($email === '' && ($password === null || $password === '')) {
            $this->command?->warn('Admin seeding skipped: set ADMIN_SEED_EMAIL and ADMIN_SEED_PASSWORD first.');

            return;
        }

        // Re-seeding must not reset a password or reactivate a disabled account.
        if ($email !== '' && AdminUser::where('email', $email)->exists()) {
            $this->command?->info('Admin account already exists; no changes made.');

            return;
        }

        $data = Validator::make([
            'name' => config('admin-seeding.name'),
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        // AdminUser's hashed cast hashes the supplied plaintext password.
        AdminUser::firstOrCreate(['email' => $data['email']], [
            'name' => $data['name'],
            'password' => $data['password'],
        ]);

        $this->command?->info('Admin account seeded.');
    }
}
