<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    // ✅ 複数形のテーブル名に修正
    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'store_id',
         'company_id', 
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

   protected static function booted()
{
    static::deleted(function ($attendance) {
        // その勤怠に紐づく Payroll を削除
        Payroll::where('attendance_id', $attendance->id)->delete();
        
        // 必要なら他の勤怠をまとめて再計算
        $month = Carbon::parse($attendance->date)->startOfMonth();
        $userId = $attendance->user_id;

        $attendances = self::where('user_id', $userId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get();

        if ($attendances->isEmpty()) return;

        $totalHours = $attendances->sum(function ($a) {
            return Carbon::parse($a->clock_in)->diffInHours(Carbon::parse($a->clock_out));
        });

        $hourlyWage = 1000; // 実際は WageHistory を参照
        $totalPay = $totalHours * $hourlyWage;

        Payroll::updateOrCreate(
            [
                'user_id' => $user->id,
                'month' => $month->toDateString(),
            ],
            [
                'company_id' => $companyId,
                'attendance_id' => $attendance->id,
                'total_hours' => $totalHours,
                'total_pay' => $totalHours * $hourlyWage,
                'hourly_wage' => $hourlyWage,
                'company_id' => $attendance->user->company_id,
            ]
        );
    });
}

}
