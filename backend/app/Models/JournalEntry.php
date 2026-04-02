<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'notary_id', 'notarial_fee', 'status', 'is_holiday',
        'execution_date', 'venue_state', 'venue_county',
        'document_description', 'risk_flag', 'verification_method',
        'thumbprint_waived', 'identification_method', 'total_fee_charged',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function signers()
    {
        return $this->hasMany(Signer::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
