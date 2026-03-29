<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signer extends Model
{
    protected $table = 'signers';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
}