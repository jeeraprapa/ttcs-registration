<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:password {email}';

    protected $description = 'Reset an administrator password and revoke existing administrator sessions.';

    public function handle(): int
    {
        $user = AdminUser::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('Administrator not found.');

            return self::FAILURE;
        }
        $password = $this->secret('New password');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => ['required', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user->update(['password' => $password, 'password_version' => $user->password_version + 1, 'remember_token' => null]);
        $this->info('Password reset; previous administrator sessions revoked.');

        return self::SUCCESS;
    }
}
