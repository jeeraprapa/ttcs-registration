<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationConsent extends Model
{
    public $timestamps = false;

    protected $fillable = ['consent_definition_id', 'is_applicable', 'accepted', 'recorded_at'];

    protected function casts(): array
    {
        return ['is_applicable' => 'boolean', 'accepted' => 'boolean', 'recorded_at' => 'datetime'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ConsentDefinition::class, 'consent_definition_id');
    }
}
