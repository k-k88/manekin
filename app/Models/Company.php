<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code', // 企業コード
        'address',
        'phone',
    ];

    // ✅ 1つの企業は複数のユーザーを持つ
    
// app/Models/Company.php
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function attendances()
    {
        return $this->hasManyThrough(Attendance::class, User::class);
    }

}
