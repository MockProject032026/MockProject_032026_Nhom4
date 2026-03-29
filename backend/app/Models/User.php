<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // 1. Cấu hình khóa chính UUID
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    // 2. Các cột có thể nạp dữ liệu (Mass Assignable)
    // Sửa theo đúng tên cột trong file SQL của bạn
    protected $fillable = [
        'id',
        'email',
        'password_hash',
        'phone_number',
        'status',
        'full_name',
        'dob',
        'address',
        'id_role',
        'commission_number',
        'commission_expiry_date',
    ];

    // 3. Các cột cần ẩn khi trả về JSON
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    // 4. Chỉ định Laravel dùng cột 'password_hash' để xác thực đăng nhập
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // 5. Cấu hình ép kiểu dữ liệu
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'commission_expiry_date' => 'date',
            'created_at' => 'datetime',
            'password_hash' => 'hashed', // Tự động hash khi lưu
        ];
    }
}