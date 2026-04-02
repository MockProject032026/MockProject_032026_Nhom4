<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaryBiometric extends Model
{
    protected $table = 'notary_biometrics';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;        

    protected $fillable = [
        'id', 'notary_id',
        'digital_signature_path',
        'thumbprint_scan_path',
    ];
}