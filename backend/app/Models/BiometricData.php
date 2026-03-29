<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiometricData extends Model
{
    protected $table = 'biometric_data';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
}
