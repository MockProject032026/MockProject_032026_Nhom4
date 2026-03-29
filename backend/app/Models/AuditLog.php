<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
}
