<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeBreakdown extends Model
{
    protected $table = 'fee_breakdowns';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
}
