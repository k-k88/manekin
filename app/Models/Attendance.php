<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'store_id',
        'company_id',
        'date',
        'clock_in',
        'clock_out',
        'hourly_wage',
    ];

    // リレーション
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }

    // 時給取得
    public function getEffectiveWageAttribute()
    {
        $wageHistory = \App\Models\WageHistory::where('user_id', $this->user_id)
            ->where('effective_from', '<=', $this->date)
            ->orderByDesc('effective_from')
            ->first();

        return $wageHistory ? $wageHistory->hourly_wage : $this->hourly_wage;
    }

    // 深夜手当込み給与
    public function getPayAttribute()
    {
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        $clockIn = Carbon::parse($this->date . ' ' . $this->clock_in);
        $clockOut = Carbon::parse($this->date . ' ' . $this->clock_out);

        if ($clockOut->lessThanOrEqualTo($clockIn)) {
            $clockOut->addDay();
        }

        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        $nightPayMinutes = 0;
        $current = $clockIn->copy();

        while ($current->lt($clockOut)) {
            $hour = (int)$current->format('H');
            if ($hour >= 22 || $hour < 5) {
                $nightPayMinutes++;
            }
            $current->addMinute();
        }

        $normalMinutes = $totalMinutes - $nightPayMinutes;
        $hourlyWage = $this->effective_wage;
        $normalPay = ($normalMinutes / 60) * $hourlyWage;
        $nightPay = ($nightPayMinutes / 60) * $hourlyWage * 1.25;

        return round($normalPay + $nightPay);
    }
}
