<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Signer extends Model
{
    protected $table = 'signers';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'journal_entry_id', 'full_name', 'email', 'phone', 'address',
        'id_type', 'id_number', 'id_issuing_authority',
        'id_expiration_date', 'customer_notes',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function biometricData()
    {
        return $this->hasOne(BiometricData::class, 'signer_id');
    }
}