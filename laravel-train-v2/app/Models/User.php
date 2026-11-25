<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'full_name', 'password_hash', 'password_status' ,'email', 'birth_date', 'user_type', 'department', 'status', 'created_at', 'update_at'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table   = 'users'; // Khai báo tên bảng
    public $timestamps = false; // Không dùng cập nhật tự động created_at và update_at

    //Khai báo kiểu dữ liệu cho users
    protected function casts(): array
    {
        return [
            'status'          => 'integer',
            'password_status' => 'integer',
            'birth_date'      => 'date:Y-m-d',
            'user_type'       => 'integer',
            'department'      => 'integer',
            'created_at'      => 'datetime:Y-m-d H:i:s',
            'update_at'       => 'datetime:Y-m-d H:i:s', 
        ];
    }
}
