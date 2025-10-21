<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',         // 店舗名
        'company_id',   // 所属企業
        'address',      // 住所（任意）
        'phone',        // 電話番号（任意）
        'status',       // active / inactive
    ];

    // 所属企業
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // 店舗に所属する社員
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // 店舗の勤怠
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
