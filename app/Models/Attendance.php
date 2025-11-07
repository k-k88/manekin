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
    // =========================
    // アクセサ：WageHistory から時給取得
    // =========================
    public function getEffectiveWageAttribute()
    {
        $wageHistory = WageHistory::where('user_id', $this->user_id)
            ->where('effective_from', '<=', $this->date)
            ->orderByDesc('effective_from')
            ->first();

        return $wageHistory ? $wageHistory->hourly_wage : $this->hourly_wage;
    }

    // 深夜手当込み給与
    // =========================
    // アクセサ：深夜手当込みの給与計算
    // =========================
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

        // 深夜時間帯（22:00〜翌5:00）
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
        $nightPay = ($nightPayMinutes / 60) * $hourlyWage * 1.25; // 深夜25%

        return round($normalPay + $nightPay);
    }

    // =========================
    // 出勤・退勤表示フォーマット
    // =========================
    public function getDisplayClockInAttribute()
    {
        return $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i') : null;
    }

    public function getDisplayClockOutAttribute()
    {
        if (!$this->clock_out) return null;

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);
        if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

        $hours = $clockOut->diffInHours($clockIn);
        $minutes = $clockOut->minute;

        $displayHour = $clockOut->hour;
        if ($hours >= 1 && $displayHour < $clockIn->hour) {
            $displayHour += 24;
        }

        return sprintf('%02d:%02d', $displayHour, $minutes);
    }

    // =========================
    // モデルイベント：Payroll 再計算
    // =========================
    protected static function booted()
    {
        static::saved(function ($attendance) {
            self::recalculatePayroll($attendance->user_id, $attendance->company_id, $attendance->date);
        });

        static::deleted(function ($attendance) {
            self::recalculatePayroll($attendance->user_id, $attendance->company_id, $attendance->date);
        });
    }

    /**
     * Payroll 再計算処理（安全版）
     */
    protected static function recalculatePayroll($userId, $companyId, $targetDate)
    {
        if (!$userId || !$targetDate) return;

        $user = User::find($userId);
        if (!$user) return;

        $month = Carbon::parse($targetDate)->startOfMonth();

        // 対象月の勤怠を取得
        $records = self::where('user_id', $userId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get();

        // 勤怠が無ければ payroll を削除
        if ($records->isEmpty()) {
            Payroll::where('user_id', $userId)
                ->where('month', $month->toDateString())
                ->delete();
            return;
        }

        // 合計時間・給与を計算
        $totalHours = 0;
        $totalPay = 0;
        $hourlyWage = 0;

        foreach ($records as $record) {
            $clockIn = Carbon::parse($record->date . ' ' . $record->clock_in);
            $clockOut = Carbon::parse($record->date . ' ' . $record->clock_out);
            if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $nightMinutes = 0;

            $current = $clockIn->copy();
            while ($current->lt($clockOut)) {
                $hour = (int)$current->format('H');
                if ($hour >= 22 || $hour < 5) $nightMinutes++;
                $current->addMinute();
            }

            $hourlyWage = $record->effective_wage ?? $record->hourly_wage ?? 0;
            $normalPay = (($totalMinutes - $nightMinutes) / 60) * $hourlyWage;
            $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;

            $totalHours += $totalMinutes / 60;
            $totalPay += $normalPay + $nightPay;
        }

        // Payrollを更新または作成（attendance_idはもう入れない）
        Payroll::updateOrCreate(
            ['user_id' => $userId, 'month' => $month->toDateString()],
            [
                'company_id' => $companyId ?? $user->company_id,
                'total_hours' => round($totalHours, 2),
                'hourly_wage' => $hourlyWage,
                'total_pay' => round($totalPay),
                'attendance_id' => null, // 削除済み勤怠への参照は持たない
            ]
        );
    }
}
