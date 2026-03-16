<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notary extends Model
{
    protected $table = 'Notaries';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['FullName', 'LicenseNumber', 'Email', 'Phone', 'StateCommissioned'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'NotaryId', 'Id');
    }
}