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
     * 💰 勤怠から給与データ生成（既存を上書きしない）
     */
    public function generatePayroll(Company $company)
    {
        $users = $company->users;

        foreach ($users as $user) {
            // 勤怠を月ごとにまとめる
            $attendancesByMonth = Attendance::where('user_id', $user->id)
                ->whereNotNull('clock_in')
                ->whereNotNull('clock_out')
                ->get()
                ->groupBy(fn($att) => Carbon::parse($att->date)->startOfMonth()->toDateString());

            foreach ($attendancesByMonth as $month => $attendances) {
                $existingPayroll = Payroll::where('user_id', $user->id)
                    ->where('month', $month)
                    ->first();

                if ($existingPayroll) continue; // 上書きしない

                $totalHours = 0;
                $totalPay = 0;
                $hourlyWage = 0;

                foreach ($attendances as $att) {
                    $clockIn = Carbon::parse($att->date . ' ' . $att->clock_in);
                    $clockOut = Carbon::parse($att->date . ' ' . $att->clock_out);
                    if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

                    $workedMinutes = $clockIn->diffInMinutes($clockOut) - ($att->break_minutes ?? 0);
                    $workedMinutes = max(0, $workedMinutes);

                    // 深夜帯(22:00〜翌5:00)
                    $nightStart = Carbon::parse($att->date . ' 22:00');
                    $nightEnd = Carbon::parse($att->date . ' 05:00')->addDay();
                    $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
                    $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
                    $nightMinutes = $overlapEnd->gt($overlapStart)
                        ? $overlapStart->diffInMinutes($overlapEnd)
                        : 0;

                    // 深夜分も休憩を引く
                    $nightMinutes = max(0, $nightMinutes - ($att->break_minutes ?? 0));
                    $normalMinutes = max(0, $workedMinutes - $nightMinutes);

                    // 💰 WageHistory / User から時給
                    $wageHistory = WageHistory::where('user_id', $user->id)
                        ->where('effective_from', '<=', $att->date)
                        ->orderByDesc('effective_from')
                        ->first();

                    $hourlyWage = $wageHistory
                        ? $wageHistory->hourly_wage
                        : ($user->hourly_wage ?? 0);

                    $normalPay = ($normalMinutes / 60) * $hourlyWage;
                    $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;

                    $totalHours += $workedMinutes / 60; // 実働時間
                    $totalPay += $normalPay + $nightPay;
                }

                Payroll::create([
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'month' => $month,
                    'total_hours' => round($totalHours, 2),
                    'hourly_wage' => $hourlyWage,
                    'total_pay' => round($totalPay),
                ]);
            }
        }

        return redirect()->back()->with('success', '勤怠から給与を生成しました（既存データは上書きされません）。');
    }

    /**
     * 💵 日別給与一覧（Blade表示用）
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
            $clockIn = Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
            $clockOut = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
            if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

            $workedMinutes = $clockIn->diffInMinutes($clockOut) - ($attendance->break_minutes ?? 0);
            $workedMinutes = max(0, $workedMinutes);

            // 深夜帯
            $nightStart = Carbon::parse($attendance->date . ' 22:00');
            $nightEnd = Carbon::parse($attendance->date . ' 05:00')->addDay();
            $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
            $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
            $nightMinutes = $overlapEnd->gt($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            $nightMinutes = max(0, $nightMinutes - ($attendance->break_minutes ?? 0));
            $normalMinutes = max(0, $workedMinutes - $nightMinutes);

            // Payroll 時給優先
            $month = Carbon::parse($attendance->date)->startOfMonth()->toDateString();
            $payroll = Payroll::where('user_id', $attendance->user_id)
                ->where('month', $month)
                ->first();

            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $payroll->hourly_wage
                ?? ($wageHistory->hourly_wage ?? ($attendance->user->hourly_wage ?? 0));

            $normalPay = ($normalMinutes / 60) * $hourlyWage;
            $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $pay = $payroll->total_pay
                ? round($payroll->total_pay / $payroll->total_hours * ($workedMinutes / 60))
                : round($normalPay + $nightPay);

            $attendance->hours = round($workedMinutes / 60, 2); // 実働時間
            $attendance->pay = $pay;
            $attendance->effective_wage = $hourlyWage;
        }

        $users = User::where('company_id', $companyId)->get();
        $totalPaySum = $attendances->sum('pay');

        return view('company.payrolls', compact('attendances', 'users', 'company', 'totalPaySum'));
    }

    /**
     * 💾 CSV出力（給与一覧）
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
            return redirect()->back()->with('error', '出力できる勤怠データがありません。');
        }

        $csvData = [];
        $csvData[] = [
            '社員名',
            '日付',
            '出勤時刻',
            '退勤時刻',
            '勤務時間(時間)',
            '休憩時間(時間)',
            '時給(円)',
            '支給額(円)',
        ];

        foreach ($attendances as $attendance) {
            $clockIn = Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
            $clockOut = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
            if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

            $workedMinutes = $clockIn->diffInMinutes($clockOut) - ($attendance->break_minutes ?? 0);
            $workedMinutes = max(0, $workedMinutes);

            $month = Carbon::parse($attendance->date)->startOfMonth()->toDateString();
            $payroll = Payroll::where('user_id', $attendance->user_id)
                ->where('month', $month)
                ->first();

            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $payroll->hourly_wage
                ?? ($wageHistory->hourly_wage ?? ($attendance->user->hourly_wage ?? 0));

            // 深夜割増計算
            $nightStart = Carbon::parse($attendance->date . ' 22:00');
            $nightEnd = Carbon::parse($attendance->date . ' 05:00')->addDay();
            $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
            $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
            $nightMinutes = $overlapEnd->gt($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            $nightMinutes = max(0, $nightMinutes - ($attendance->break_minutes ?? 0));
            $normalMinutes = max(0, $workedMinutes - $nightMinutes);

            $normalPay = ($normalMinutes / 60) * $hourlyWage;
            $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $pay = round($normalPay + $nightPay);

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
        $csv = "\xEF\xBB\xBF"; // BOM付きUTF-8
        foreach ($csvData as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    /**
     * 🔁 今月の給与データ再計算
     */
    public function recalculatePayroll(Company $company)
    {
        $this->generatePayroll($company);
        return redirect()->route('company.payrolls', $company->id)
            ->with('success', '給与データを再計算しました。');
    }
}
