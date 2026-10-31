<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $attributes = ['is_active' => true, 'password_version' => 1];

    protected $fillable = ['name', 'email', 'password', 'is_active', 'password_version'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'password_version' => 'integer'];
    }
}
