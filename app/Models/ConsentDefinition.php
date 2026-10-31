<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentDefinition extends Model
{
    protected $fillable = ['code', 'version', 'text_snapshot'];
}
