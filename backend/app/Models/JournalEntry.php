<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $table = 'journal_entries'; 
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = []; 
}
