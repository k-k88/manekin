<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\WageHistory;
use App\Models\Payroll;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * 勤怠 → 給与生成（既存はスキップ）
     */
    public function generatePayroll(Company $company)
    {
        $users = $company->users;

        foreach ($users as $user) {
            $attendancesByMonth = Attendance::where('user_id', $user->id)
                ->whereNotNull('clock_in')
                ->whereNotNull('clock_out')
                ->orderBy('date')
                ->get()
                ->groupBy(fn($att) => $att->date->copy()->startOfMonth()->toDateString());

            foreach ($attendancesByMonth as $month => $attendances) {
                // 既に給与があればスキップ
                if (Payroll::where('user_id', $user->id)->where('month', $month)->exists()) {
                    continue;
                }

                $totalHours = 0;
                $totalPay   = 0;
                $lastHourlyWage = 0;

                foreach ($attendances as $att) {
                    $clockIn  = $att->clock_in;
                    $clockOut = $att->clock_out;

                    if ($clockOut->lessThanOrEqualTo($clockIn)) {
                        $clockOut = $clockOut->copy()->addDay();
                    }

                    // 勤務時間関連
                    $totalMinutes = $clockIn->diffInMinutes($clockOut);
                    $breakMinutes = $att->break_minutes ?? 0;
                    $workedMinutes = max(0, $totalMinutes - $breakMinutes);

                    // 深夜帯計算
                    $nightStart = $clockIn->copy()->setTime(22, 0);
                    $nightEnd   = $clockIn->copy()->setTime(5, 0)->addDay();
                    $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
                    $overlapEnd   = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;

                    $nightMinutes = $overlapEnd->gt($overlapStart)
                        ? $overlapStart->diffInMinutes($overlapEnd)
                        : 0;

                    // 深夜帯に休憩を按分
                    $nightRatio = $nightMinutes / max($totalMinutes, 1);
                    $nightMinutes -= round($breakMinutes * $nightRatio);

                    $normalMinutes = $workedMinutes - $nightMinutes;

                    // 時給履歴
                    $wageHistory = WageHistory::where('user_id', $user->id)
                        ->where('effective_from', '<=', $att->date)
                        ->where(function ($q) use ($att) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', $att->date);
                        })
                        ->orderByDesc('effective_from')
                        ->first();

                    $hourlyWage = $wageHistory->hourly_wage ?? 0;
                    $lastHourlyWage = $hourlyWage;

                    // ▼▼▼ 残業 & 深夜計算（統一版） ▼▼▼
                    $regularMinutes      = 480; // 8時間
                    $overtimeMinutes     = max(0, $workedMinutes - $regularMinutes);
                    $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

                    $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
                    $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
                    $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

                    $totalPay += $regularPay + $nightPay + $overtimePay;
                    $totalHours += $workedMinutes / 60;
                    // ▲▲▲ ここまで統一 ▲▲▲
                }

                Payroll::create([
                    'user_id'      => $user->id,
                    'company_id'   => $company->id,
                    'month'        => $month,
                    'total_hours'  => round($totalHours, 2),
                    'hourly_wage'  => $lastHourlyWage,
                    'total_pay'    => round($totalPay),
                ]);
            }
        }

        return back()->with('success', '勤怠から給与を生成しました（既存データはスキップ）。');
    }

    /**
     * 給与一覧（画面表示）
     */
    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);

        $query = Attendance::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out');

        if ($request->month) {
            $query->where('date', 'like', $request->month . '%');
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $attendances = $query->orderBy('date', 'desc')->get();

        foreach ($attendances as $attendance) {
            $clockIn  = $attendance->clock_in;
            $clockOut = $attendance->clock_out;

            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }

            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $breakMinutes = $attendance->break_minutes ?? 0;
            $workedMinutes = max(0, $totalMinutes - $breakMinutes);

            // 深夜帯
            $nightStart = $clockIn->copy()->setTime(22, 0);
            $nightEnd   = $clockIn->copy()->setTime(5, 0)->addDay();
            $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
            $overlapEnd   = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;

            $nightMinutes = $overlapEnd->gt($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            $nightRatio = $nightMinutes / max($totalMinutes, 1);
            $nightMinutes -= round($breakMinutes * $nightRatio);

            $normalMinutes = $workedMinutes - $nightMinutes;

            // 時給取得
            $month = $attendance->date->copy()->startOfMonth()->toDateString();
            $payroll = Payroll::where('user_id', $attendance->user_id)
                ->where('month', $month)
                ->first();

            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->where(function ($q) use ($attendance) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $attendance->date);
                })
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $payroll->hourly_wage ?? ($wageHistory->hourly_wage ?? 0);

            // ▼▼▼ 残業 & 深夜計算（統一版） ▼▼▼
            $regularMinutes      = 480;
            $overtimeMinutes     = max(0, $workedMinutes - $regularMinutes);
            $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

            $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
            $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

            $pay = round($regularPay + $nightPay + $overtimePay);
            // ▲▲▲ 統一終了 ▲▲▲

            $attendance->hours = round($workedMinutes / 60, 2);
            $attendance->pay   = $pay;
            $attendance->effective_wage = $hourlyWage;
        }

        $users = User::where('company_id', $companyId)->get();
        $totalPaySum = $attendances->sum('pay');

        return view('company.payrolls', compact('attendances', 'users', 'company', 'totalPaySum'));
    }

    /**
     * CSV出力
     */
    public function payrollsCsv(Company $company)
    {
        $attendances = Attendance::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->orderBy('date', 'desc')
            ->get();

        if ($attendances->isEmpty()) {
            return back()->with('error', '出力できる勤怠データがありません。');
        }

        $csvData = [];
        $csvData[] = [
            '社員名','日付','出勤時刻','退勤時刻',
            '勤務時間(時間)','休憩時間(時間)','時給(円)','支給額(円)',
        ];

        foreach ($attendances as $attendance) {
            $clockIn  = $attendance->clock_in;
            $clockOut = $attendance->clock_out;

            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }

            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $breakMinutes = $attendance->break_minutes ?? 0;
            $workedMinutes = max(0, $totalMinutes - $breakMinutes);

            // 深夜帯
            $nightStart = $clockIn->copy()->setTime(22, 0);
            $nightEnd   = $clockIn->copy()->setTime(5, 0)->addDay();
            $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
            $overlapEnd   = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;

            $nightMinutes = $overlapEnd->gt($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            $nightRatio = $nightMinutes / max($totalMinutes, 1);
            $nightMinutes -= round($breakMinutes * $nightRatio);

            $normalMinutes = $workedMinutes - $nightMinutes;

            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->where(function ($q) use ($attendance) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $attendance->date);
                })
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $wageHistory->hourly_wage ?? 0;

            // ▼▼▼ 残業 & 深夜計算（統一版） ▼▼▼
            $regularMinutes      = 480;
            $overtimeMinutes     = max(0, $workedMinutes - $regularMinutes);
            $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

            $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
            $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

            $pay = round($regularPay + $nightPay + $overtimePay);
            // ▲▲▲ 統一終了 ▲▲▲

            $csvData[] = [
                $attendance->user->name,
                $attendance->date,
                $attendance->clock_in,
                $attendance->clock_out,
                round($workedMinutes / 60, 2),
                round(($attendance->break_minutes ?? 0) / 60, 2),
                $hourlyWage,
                $pay,
            ];
        }

        $filename = 'payroll_' . now()->format('Ymd_His') . '.csv';
        $csv = "\xEF\xBB\xBF";
        foreach ($csvData as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * 再計算
     */
    public function recalculatePayroll(Company $company)
    {
        $this->generatePayroll($company);
        return redirect()->route('company.payrolls', $company->id)
            ->with('success', '給与データを再計算しました。');
    }
}
