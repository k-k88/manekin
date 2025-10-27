<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'store_id',
        'date',
        'clock_in',
        'clock_out',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
     public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }

    // ✅ 勤怠削除時に給与も削除
     protected static function booted()
{
    static::deleted(function ($attendance) {
        $month = Carbon::parse($attendance->date)->startOfMonth();
        $userId = $attendance->user_id;

        $totalHours = DB::table('attendance')
            ->where('user_id', $userId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get()
            ->sum(function ($record) {
                return Carbon::parse($record->clock_in)
                    ->diffInHours(Carbon::parse($record->clock_out));
            });

        $hourlyWage = 1000;
        $totalPay = $totalHours * $hourlyWage;

        \App\Models\Payroll::updateOrCreate(
            [
                'user_id' => $userId,
                'month' => $month->toDateString(),
            ],
            [
                'total_hours' => $totalHours,
                'total_pay' => $totalPay,
                'hourly_wage' => $hourlyWage,
                'company_id' => $attendance->user->company_id, // 修正
            ]
        );
    });
}


}
