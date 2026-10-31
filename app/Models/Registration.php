<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['idempotency_key', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['session_date_snapshot' => 'date', 'guest_count' => 'integer', 'seat_count' => 'integer'];
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(RegistrationConsent::class);
    }

    public function confirmationEmails(): HasMany
    {
        return $this->hasMany(ConfirmationEmail::class);
    }
}
