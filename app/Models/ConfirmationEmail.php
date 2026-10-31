<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfirmationEmail extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime', 'lease_until' => 'datetime', 'provider_accepted_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
