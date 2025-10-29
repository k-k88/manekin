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
    static::saved(function ($attendance) {
        $user = $attendance->user;
        if (!$user) return;

        $companyId = $attendance->company_id ?? $user->company_id ?? 1;

        $month = Carbon::parse($attendance->date)->startOfMonth();
        $totalHours = DB::table('attendance')
            ->where('user_id', $user->id)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get()
            ->sum(function ($record) {
                return Carbon::parse($record->clock_in)
                    ->diffInHours(Carbon::parse($record->clock_out));
            });

        $hourlyWage = \App\Models\Payroll::where('user_id', $user->id)
            ->orderByDesc('month')
            ->value('hourly_wage') ?? $user->hourly_wage ?? 1000;

        \App\Models\Payroll::updateOrCreate(
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
            ]
        );
    });
}


}
