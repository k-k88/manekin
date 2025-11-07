<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\WageHistory;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
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

                    $totalMinutes = $clockIn->diffInMinutes($clockOut);

                    // 深夜帯(22:00〜翌5:00)
                    $nightStart = Carbon::parse($att->date . ' 22:00');
                    $nightEnd = Carbon::parse($att->date . ' 05:00')->addDay();
                    $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
                    $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
                    $nightMinutes = $overlapEnd->gt($overlapStart)
                        ? $overlapStart->diffInMinutes($overlapEnd)
                        : 0;

                    $normalMinutes = max(0, $totalMinutes - $nightMinutes);

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

                    $totalHours += $totalMinutes / 60;
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

            $nightStart = Carbon::parse($attendance->date . ' 22:00');
            $nightEnd = Carbon::parse($attendance->date . ' 05:00')->addDay();
            $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
            $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
            $nightMinutes = $overlapEnd->gt($overlapStart)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $normalMinutes = max(0, $totalMinutes - $nightMinutes);

            // 対応する Payroll レコードを取得
            $month = Carbon::parse($attendance->date)->startOfMonth()->toDateString();
            $payroll = Payroll::where('user_id', $attendance->user_id)
                ->where('month', $month)
                ->first();

            // 💰 時給は Payroll 優先
            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $payroll->hourly_wage
                ?? ($wageHistory->hourly_wage ?? ($attendance->user->hourly_wage ?? 0));

            $normalPay = ($normalMinutes / 60) * $hourlyWage;
            $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $pay = $payroll->total_pay
                ? round($payroll->total_pay / $payroll->total_hours * ($totalMinutes / 60))
                : round($normalPay + $nightPay);

            $attendance->hours = round($totalMinutes / 60, 2);
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
        '時給(円)',
        '支給額(円)',
    ];

    foreach ($attendances as $attendance) {
        $clockIn = Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
        $clockOut = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
        if ($clockOut->lessThanOrEqualTo($clockIn)) $clockOut->addDay();

        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        $hours = round($totalMinutes / 60, 2);

        // 対応する Payroll・WageHistory を取得
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

        $normalMinutes = max(0, $totalMinutes - $nightMinutes);
        $normalPay = ($normalMinutes / 60) * $hourlyWage;
        $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
        $pay = round($normalPay + $nightPay);

        $csvData[] = [
            $attendance->user->name,
            $attendance->date,
            $attendance->clock_in,
            $attendance->clock_out,
            $hours,
            $hourlyWage,
            $pay,
        ];
    }

    // CSV出力処理
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
