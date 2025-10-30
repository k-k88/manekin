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
        'hourly_wage', // 保存済み時給
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

<<<<<<< HEAD
    protected static function booted()
    {
        static::saved(function ($attendance) {
            $user = $attendance->user;
            if (!$user) return;

            $companyId = $attendance->company_id ?? $user->company_id ?? 1;
            $month = Carbon::parse($attendance->date)->startOfMonth();

            // ✅ 月内すべての勤怠データを取得
            $records = DB::table('attendance')
                ->where('user_id', $user->id)
                ->whereMonth('date', $month->month)
                ->whereYear('date', $month->year)
                ->whereNotNull('clock_in')
                ->whereNotNull('clock_out')
                ->get();

            $totalPay = 0;
            $totalMinutes = 0;

            foreach ($records as $record) {
                $in = Carbon::parse($record->clock_in);
                $out = Carbon::parse($record->clock_out);

                // 🔸 勤務時間が正しく計算できるようにする（同日内のみ想定）
                if ($out->lt($in)) {
                    // 同日のみにするためスキップ
                    continue;
                }

                $hourlyWage = \App\Models\Payroll::where('user_id', $user->id)
                    ->orderByDesc('month')
                    ->value('hourly_wage') ?? $user->hourly_wage ?? 1000;

                // ✅ 分単位でのループ計算（22:00～5:00は1.25倍）
                $current = $in->copy();
                while ($current->lt($out)) {
                    $next = $current->copy()->addMinute();
                    $hour = $current->format('H');

                    // 深夜時間帯（22:00～翌5:00）は1.25倍
                    if ((int)$hour >= 22 || (int)$hour < 5) {
                        $totalPay += ($hourlyWage / 60) * 1.25; // 1分あたり
                    } else {
                        $totalPay += ($hourlyWage / 60);
                    }

                    $totalMinutes++;
                    $current = $next;
                }
            }

            $totalHours = round($totalMinutes / 60, 2);

            \App\Models\Payroll::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'month' => $month->toDateString(),
                ],
                [
                    'company_id' => $companyId,
                    'attendance_id' => $attendance->id,
                    'total_hours' => $totalHours,
                    'total_pay' => round($totalPay),
                    'hourly_wage' => $user->hourly_wage ?? 1000,
                ]
            );
=======
    // =========================
    // アクセサ：WageHistory から日付に応じた時給を取得
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
    // アクセサ：勤務時間と時給から給与を計算
    // =========================
    public function getPayAttribute()
    {
        if ($this->clock_in && $this->clock_out) {
            $hours = Carbon::parse($this->clock_in)
                ->diffInMinutes(Carbon::parse($this->clock_out)) / 60;

            return $hours * $this->effective_wage;
        }
        return 0;
    }

    // =========================
    // モデルイベント：保存後に Payroll を作成（既存は上書きしない）
    // =========================
    protected static function booted()
    {
        static::saved(function ($attendance) {
            $user = $attendance->user;
            if (!$user) return;

            $companyId = $attendance->company_id ?? $user->company_id ?? 1;
            $month = Carbon::parse($attendance->date)->startOfMonth();

            // 勤務時間の合計
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

            // 既存 Payroll があるかチェック
            $existingPayroll = \App\Models\Payroll::where('user_id', $user->id)
                ->where('month', $month->toDateString())
                ->first();

            if (!$existingPayroll) {
                // まだ Payroll がなければ作成
                \App\Models\Payroll::create([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'month' => $month->toDateString(),
                    'total_hours' => $totalHours,
                    'hourly_wage' => $attendance->hourly_wage, // Attendance に保存済み時給
                    'total_pay' => $totalHours * $attendance->hourly_wage,
                ]);
            }
>>>>>>> 25fddf929f22fc2de6050cc34b8dcfc8535b4094
        });
    }
}
