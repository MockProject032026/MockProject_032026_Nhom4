<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'Services';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['ServiceName', 'BaseFee', 'Description'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'ServiceId', 'Id');
    }
}