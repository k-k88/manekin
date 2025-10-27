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
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // ✅ 1つの企業は複数の店舗を持つ
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    // ✅ 1つの企業はユーザーを通じて複数の勤怠データを持つ
    public function attendances()
    {
        return $this->hasManyThrough(Attendance::class, User::class);
    }
    

    // ✅ 1つの企業は複数の給与データ（Payroll）を持つ
    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }
}
