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

    /**
     * 企業に所属するユーザー（従業員）
     */
    public function employees()
    {
        // Employee モデルの代わりに User モデルを使用
        return $this->hasMany(User::class, 'company_id', 'id');
    }

    /**
     * 企業に紐づく勤怠データ
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'company_id', 'id');
    }

    /**
     * 企業に紐づく給与データ
     */
    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'company_id', 'id');
    }

    /**
     * 企業に紐づく店舗データ
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    /**
     * 企業に紐づくユーザー（別名）
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
