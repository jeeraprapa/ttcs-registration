<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;

class DisableAdmin extends Command
{
    protected $signature = 'admin:disable {email}';

    protected $description = 'Deactivate an administrator and revoke existing sessions.';

    public function handle(): int
    {
        $user = AdminUser::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('Administrator not found.');

            return self::FAILURE;
        }
        $user->update(['is_active' => false, 'password_version' => $user->password_version + 1, 'remember_token' => null]);
        $this->info('Administrator deactivated.');

        return self::SUCCESS;
    }
}
