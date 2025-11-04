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
        'company_id',
        'date',
        'clock_in',
        'clock_out',
        'hourly_wage',
    ];
 
    // =========================
    // リレーション
    // =========================
    public function user()
    {
        return $this->belongsTo(User::class);
    }
 
    public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }
 
    // =========================
    // アクセサ：WageHistory から時給取得
    // =========================
    public function getEffectiveWageAttribute()
    {
        $wageHistory = \App\Models\WageHistory::where('user_id', $this->user_id)
            ->where('effective_from', '<=', $this->date)
            ->orderByDesc('effective_from')
            ->first();
 
        return $wageHistory ? $wageHistory->hourly_wage : $this->hourly_wage;
    }
 
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
            $clockOut->addDay(); // 日跨ぎ対応
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
            self::recalculatePayroll($attendance);
        });
 
        static::deleted(function ($attendance) {
            self::recalculatePayroll($attendance);
        });
    }
 
    /**
     * Payroll 再計算処理
     */
    protected static function recalculatePayroll($attendance)
    {
        if (!$attendance->user_id || !$attendance->date) return;
 
        $user = $attendance->user ?? \App\Models\User::find($attendance->user_id);
        if (!$user) return;
 
        $companyId = $attendance->company_id ?? $user->company_id ?? 1;
        $month = Carbon::parse($attendance->date)->startOfMonth();
 
        $records = self::where('user_id', $user->id)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get();
 
        $totalHours = 0;
        $totalPay = 0;
        $hourlyWage = 0;
 
        foreach ($records as $record) {
            $clockIn = Carbon::parse($record->date . ' ' . $record->clock_in);
            $clockOut = Carbon::parse($record->date . ' ' . $record->clock_out);
            if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();
 
            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $nightMinutes = 0;
 
            // 深夜時間帯（22:00〜翌5:00）
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
 
        $existingPayroll = \App\Models\Payroll::where('user_id', $user->id)
            ->where('month', $month->toDateString())
            ->first();
 
        if ($existingPayroll) {
            $existingPayroll->update([
                'total_hours' => round($totalHours, 2),
                'total_pay' => round($totalPay),
                'hourly_wage' => $hourlyWage,
                'company_id' => $companyId,
            ]);
        } else {
            \App\Models\Payroll::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'attendance_id' => $attendance->id,
                'month' => $month->toDateString(),
                'total_hours' => round($totalHours, 2),
                'hourly_wage' => $hourlyWage,
                'total_pay' => round($totalPay),
            ]);
        }
    }
}
 
 