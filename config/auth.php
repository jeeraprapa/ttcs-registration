<?php

use App\Models\AdminUser;

return [
    'defaults' => ['guard' => 'admin', 'passwords' => 'admins'],
    'guards' => ['admin' => ['driver' => 'session', 'provider' => 'admins']],
    'providers' => ['admins' => ['driver' => 'eloquent', 'model' => AdminUser::class]],
    'passwords' => [],
    'password_timeout' => 10800,
];
