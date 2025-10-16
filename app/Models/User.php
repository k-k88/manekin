<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'line_user_id',
        'phone',
        'role',
        'store_id',
        'company_id',
        'hire_date',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ 勤怠データ（1ユーザー対多勤怠）
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // ✅ 所属店舗（1ユーザー対1店舗）
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // ✅ 所属企業（1ユーザー対1企業）
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
