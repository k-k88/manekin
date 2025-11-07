<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;
    protected $table = 'payroll';
    protected $fillable = [
        'user_id',
        'company_id',
        'attendance_id',
        'hourly_wage',
        'total_hours',
        'total_pay',
        'month',
    ];

    /**
     * 🔹 ユーザー（社員）情報
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 🔹 勤怠情報
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
