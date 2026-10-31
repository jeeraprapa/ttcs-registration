<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email?} {--name=}';

    protected $description = 'Create an administrator using a hidden password prompt.';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email'))));
        $name = $this->option('name') ?: $this->ask('Name');
        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation], [
            'email' => ['required', 'email', 'max:254', 'unique:admin_users,email'], 'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        AdminUser::create(['email' => $email, 'name' => $name, 'password' => $password]);
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
