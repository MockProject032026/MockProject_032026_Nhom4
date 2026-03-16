<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'Clients';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $fillable = ['FullName', 'Email', 'Phone', 'SSN', 'Address'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'ClientId', 'Id');
    }
}