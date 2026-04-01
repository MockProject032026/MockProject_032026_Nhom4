<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BiometricData extends Model
{
    protected $table = 'biometric_data';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'signer_id', 'signature_image', 'thumbprint_image',
        'biometric_match_hash', 'capture_device_id', 'capture_location',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function signer()
    {
        return $this->belongsTo(Signer::class);
    }
}