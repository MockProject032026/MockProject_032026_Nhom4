<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'Transactions';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['NotaryId', 'ClientId', 'ServiceId', 'TotalFee', 'Status', 'Notes'];

    public function notary()
    {
        return $this->belongsTo(Notary::class, 'NotaryId', 'Id');
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'ClientId', 'Id');
    }
    public function service()
    {
        return $this->belongsTo(Service::class, 'ServiceId', 'Id');
    }
}