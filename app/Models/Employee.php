<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees'; // ← テーブル名が employees の場合
    protected $fillable = [
        'user_id',
        'company_id',
        'position',
        'department',
        'employment_status',
        'hire_date',
        // 他に必要なカラムがあればここに追加
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
