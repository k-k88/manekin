<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Payroll;
use App\Models\WageHistory;
use App\Models\User;
use App\Models\Shift;

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
        'break_start',
        'break_end',
        'hourly_wage',
        'break_minutes',
        'late_flag',
        'early_leave_flag',
    ];

 protected $casts = [
    'date' => 'date',
    'clock_in' => 'string',
    'clock_out' => 'string',
    'break_start' => 'string',
    'break_end' => 'string',
    'late_flag' => 'boolean',
    'early_leave_flag' => 'boolean',
];


    // ----------------------------
    // 🔹 リレーション
    // ----------------------------
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }

    // ==============================
    // ⏰ 29:00対応 Carbon変換
    // ==============================
    protected function parseTimeWithOverflow($baseDate, $time)
    {
        if (!$time) return null;
        if ($time instanceof Carbon) return $time;

        [$hour, $minute] = explode(':', $time);
        $carbon = Carbon::parse($baseDate)->setTime(0, 0);
        if ((int)$hour >= 24) {
            $carbon->addDay();
            $hour -= 24;
        }
        return $carbon->setTime((int)$hour, (int)$minute);
    }

    // ----------------------------
    // 💰 有効時給
    // ----------------------------
    public function getEffectiveWageAttribute()
    {
        $wageHistory = WageHistory::getWageForDate($this->user_id, $this->date);
        return $wageHistory ? $wageHistory->hourly_wage : $this->hourly_wage;
    }

    // ----------------------------
    // ☕ 休憩分数
    // ----------------------------
    public function calculateBreakMinutes(): int
    {
        if ($this->break_start && $this->break_end) {
            $bStart = $this->parseTimeWithOverflow($this->date, $this->break_start);
            $bEnd   = $this->parseTimeWithOverflow($this->date, $this->break_end);
            if ($bEnd->lessThanOrEqualTo($bStart)) $bEnd->addDay();
            return $bStart->diffInMinutes($bEnd);
        }
        return 0;
    }

    // ----------------------------
    // ⏱ 勤務総分数
    // ----------------------------
    public function getTotalWorkMinutes(): int
    {
        if (!$this->clock_in || !$this->clock_out) return 0;

        $in  = $this->parseTimeWithOverflow($this->date, $this->clock_in);
        $out = $this->parseTimeWithOverflow($this->date, $this->clock_out);
        if ($out->lessThanOrEqualTo($in)) $out->addDay();

        $breakMinutes = $this->break_minutes ?? $this->calculateBreakMinutes();
        return max(0, $in->diffInMinutes($out) - $breakMinutes);
    }

    // ----------------------------
    // 🌙 深夜勤務分
    // ----------------------------
    protected function calculateNightMinutes(Carbon $start, Carbon $end): int
    {
        $nightStart = $start->copy()->setTime(22, 0);
        $nightEnd   = $start->copy()->addDay()->setTime(5, 0);

        $overlapStart = $start->max($nightStart);
        $overlapEnd   = $end->min($nightEnd);

        return $overlapStart->lt($overlapEnd)
            ? $overlapStart->diffInMinutes($overlapEnd)
            : 0;
    }

    // ----------------------------
    // 💴 給与計算
    // ----------------------------
    public function getPayAttribute(): int
    {
        if (!$this->clock_in || !$this->clock_out) return 0;

        $in  = $this->parseTimeWithOverflow($this->date, $this->clock_in);
        $out = $this->parseTimeWithOverflow($this->date, $this->clock_out);
        if ($out->lessThanOrEqualTo($in)) $out->addDay();

        $totalMinutes = $this->getTotalWorkMinutes();
        $nightMinutes = $this->calculateNightMinutes($in, $out);
        $normalMinutes = max(0, $totalMinutes - $nightMinutes);

        $hourlyWage = $this->effective_wage;
        $normalPay  = ($normalMinutes / 60) * $hourlyWage;
        $nightPay   = ($nightMinutes / 60) * $hourlyWage * 1.25;

        return (int) round($normalPay + $nightPay);
    }

    // ----------------------------
    // 🕒 勤務時間（時間単位）
    // ----------------------------
    public function getWorkedHoursAttribute(): float
    {
        if (!$this->clock_in || !$this->clock_out) return 0;

        $in  = $this->parseTimeWithOverflow($this->date, $this->clock_in);
        $out = $this->parseTimeWithOverflow($this->date, $this->clock_out);
        if ($out->lessThanOrEqualTo($in)) $out->addDay();

        $breakMinutes = $this->break_minutes ?? $this->calculateBreakMinutes();
        return round(($in->diffInMinutes($out) - $breakMinutes) / 60, 2);
    }

    // ----------------------------
    // ⏰ 遅刻分（分）
// ----------------------------
// ⏰ 遅刻分（分）
// ----------------------------
public function getLateMinutesAttribute(): int
{
    if (!$this->clock_in) return 0;

    $shift = $this->shiftOfDay()->first();
    if (!$shift || $shift->is_day_off) return 0;

    // シフト開始
    $shiftStart = Carbon::parse("{$shift->shift_date} {$shift->start_time}");
    // 打刻
    $clockIn = Carbon::parse("{$this->date->format('Y-m-d')} {$this->clock_in}");

    // 正しい差分は shiftStart → clockIn
    return $clockIn->gt($shiftStart)
        ? $shiftStart->diffInMinutes($clockIn)
        : 0;
}







// ----------------------------
// ⏰ 早退分（分）
// ----------------------------
public function getEarlyLeaveMinutesAttribute(): int
{
    if (!$this->clock_out) return 0;

    $shift = $this->shiftOfDay()->first();
    if (!$shift || $shift->is_day_off) return 0;

    $shiftEnd = Carbon::parse($shift->shift_date . ' ' . $shift->end_time);
    $clockOut = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->clock_out);

   return $clockOut->lt($shiftEnd)
    ? $clockOut->diffInMinutes($shiftEnd)
    : 0;


}





    // ----------------------------
    // 🔄 モデルイベント
    // ----------------------------
    protected static function booted()
    {
        static::saving(function ($attendance) {
            if ($attendance->break_start && $attendance->break_end) {
                $attendance->break_minutes = $attendance->calculateBreakMinutes();
            }
        });

        static::saved(function ($attendance) {
            self::recalculatePayroll($attendance->user_id, $attendance->company_id, $attendance->date);
        });

        static::deleted(function ($attendance) {
            self::recalculatePayroll($attendance->user_id, $attendance->company_id, $attendance->date);
        });
    }

    // ----------------------------
    // 📆 月次給与再計算
    // ----------------------------
    protected static function recalculatePayroll($userId, $companyId, $targetDate)
    {
        if (!$userId || !$targetDate) return;

        $user = User::find($userId);
        if (!$user) return;

        $monthStart = Carbon::parse($targetDate)->startOfMonth();

        $records = self::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereMonth('date', $monthStart->month)
            ->whereYear('date', $monthStart->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get();

        if ($records->isEmpty()) {
            Payroll::where('user_id', $userId)
                ->where('month', $monthStart->format('Y-m'))
                ->delete();
            return;
        }

        $totalHours = 0;
        $totalPay   = 0;
        $hourlyWage = 0;

        foreach ($records as $record) {
            $in  = $record->parseTimeWithOverflow($record->date, $record->clock_in);
            $out = $record->parseTimeWithOverflow($record->date, $record->clock_out);
            if ($out->lessThanOrEqualTo($in)) $out->addDay();

            $totalMinutes = $record->getTotalWorkMinutes();
            $nightMinutes = $record->calculateNightMinutes($in, $out);
            $normalMinutes = max(0, $totalMinutes - $nightMinutes);

            $hourlyWage = $record->effective_wage ?? $record->hourly_wage ?? 0;

            $normalPay = ($normalMinutes / 60) * $hourlyWage;
            $nightPay  = ($nightMinutes / 60) * $hourlyWage * 1.25;

            $totalHours += $totalMinutes / 60;
            $totalPay   += $normalPay + $nightPay;
        }

        Payroll::updateOrCreate(
            ['user_id' => $userId, 'month' => $monthStart->format('Y-m-01')],
            [
                'company_id'    => $companyId,
                'total_hours'   => round($totalHours, 2),
                'hourly_wage'   => $hourlyWage,
                'total_pay'     => round($totalPay),
                'attendance_id' => null,
            ]
        );
    }
public function shiftOfDay()
{
    $date = $this->date instanceof \Carbon\Carbon
        ? $this->date->format('Y-m-d')
        : $this->date;

    return $this->hasOne(Shift::class, 'user_id', 'user_id')
        ->whereDate('shift_date', $date)
        ->where('status', 'approved');
}


}
