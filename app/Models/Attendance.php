<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Payroll;
use App\Models\WageHistory;
use App\Models\User;

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
    ];

    protected $casts = [
        'clock_in'   => 'datetime',
        'clock_out'  => 'datetime',
        'break_start'=> 'datetime',
        'break_end'  => 'datetime',
        'date'       => 'date',
    ];

    // ----------------------------
    // リレーション
    // ----------------------------
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }

    // ----------------------------
    // 有効な時給を取得
    // ----------------------------
    public function getEffectiveWageAttribute()
    {
        $wageHistory = WageHistory::where('user_id', $this->user_id)
            ->where('effective_from', '<=', $this->date)
            ->orderByDesc('effective_from')
            ->first();

        return $wageHistory ? $wageHistory->hourly_wage : $this->hourly_wage;
    }

    // ----------------------------
    // 休憩時間（分）を算出
    // ----------------------------
    public function calculateBreakMinutes(): int
    {
        if ($this->break_start && $this->break_end) {
            $bStart = $this->break_start->copy();
            $bEnd   = $this->break_end->copy();

            if ($bEnd->lessThanOrEqualTo($bStart)) {
                $bEnd->addDay();
            }

            return $bStart->diffInMinutes($bEnd);
        }

        return 0;
    }

    // ----------------------------
    // 出勤・退勤の表示フォーマット
    // ----------------------------
    public function getDisplayClockInAttribute()
    {
        return $this->clock_in?->copy()->format('H:i');
    }

    public function getDisplayClockOutAttribute()
    {
        if (!$this->clock_out) return null;

        $clockOut = $this->clock_out->copy();
        if ($clockOut->lessThanOrEqualTo($this->clock_in)) {
            $clockOut->addDay();
        }

        return $clockOut->format('H:i');
    }

    // ----------------------------
    // 勤務総分数（休憩除く）
    // ----------------------------
    public function getTotalWorkMinutes(): int
    {
        if (!$this->clock_in || !$this->clock_out) return 0;

        $clockOut = $this->clock_out->copy();
        if ($clockOut->lessThanOrEqualTo($this->clock_in)) $clockOut->addDay();

        $breakMinutes = $this->break_minutes ?? $this->calculateBreakMinutes();
        return $this->clock_in->diffInMinutes($clockOut) - $breakMinutes;
    }

    // ----------------------------
    // 深夜勤務分の計算（22:00～翌5:00）
    // ----------------------------
    protected function calculateNightMinutes(Carbon $start, Carbon $end): int
    {
        $nightStart = $start->copy()->setTime(22, 0);
        $nightEnd   = $start->copy()->addDay()->setTime(5, 0);

        $overlapStart = $start->max($nightStart);
        $overlapEnd   = $end->min($nightEnd);

        return $overlapStart->lt($overlapEnd) ? $overlapStart->diffInMinutes($overlapEnd) : 0;
    }

    // ----------------------------
    // 勤務時間・給与計算（深夜手当込み）
    // ----------------------------
    public function getPayAttribute(): int
    {
        if (!$this->clock_in || !$this->clock_out) return 0;

        $totalMinutes = $this->getTotalWorkMinutes();
        $nightMinutes = $this->calculateNightMinutes($this->clock_in, $this->clock_out);
        $normalMinutes = max(0, $totalMinutes - $nightMinutes);
        $hourlyWage = $this->effective_wage;

        $normalPay = ($normalMinutes / 60) * $hourlyWage;
        $nightPay  = ($nightMinutes / 60) * $hourlyWage * 1.25;

        return (int) round($normalPay + $nightPay);
    }

    // ----------------------------
    // モデルイベントで自動計算・給与再計算
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
    // 月ごとの給与再計算処理
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
            $totalMinutes = $record->getTotalWorkMinutes();
            $nightMinutes = $record->calculateNightMinutes($record->clock_in, $record->clock_out);
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
               'company_id'   => $companyId,
               'total_hours'  => round($totalHours, 2),
               'hourly_wage'  => $hourlyWage,
               'total_pay'    => round($totalPay),
               'attendance_id'=> null,
           ]
       );
    }
}
