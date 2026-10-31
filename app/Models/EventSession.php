<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    protected $fillable = ['event_key', 'local_date', 'local_start_time', 'timezone', 'venue', 'is_open', 'capacity', 'reserved_seats'];

    protected function casts(): array
    {
        return ['local_date' => 'date', 'is_open' => 'boolean', 'capacity' => 'integer', 'reserved_seats' => 'integer'];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
