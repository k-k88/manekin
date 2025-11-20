<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\WageHistory;
use App\Models\Shift;
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
                ->orderBy('date')
                ->get()
                ->groupBy(fn($att) => Carbon::parse($att->date)->format('Y-m-01'));

            foreach ($attendancesByMonth as $month => $attendances) {

                if (Payroll::where('user_id', $user->id)->where('month', $month)->exists()) {
                    continue;
                }

                $totalHours = 0;
                $totalPay   = 0;
                $lastHourlyWage = 0;

                foreach ($attendances as $att) {

                    $baseDate = Carbon::parse($att->date)->format('Y-m-d');

                    $clockIn  = $att->clock_in ? Carbon::parse("$baseDate {$att->clock_in}") : null;
                    $clockOut = $att->clock_out ? Carbon::parse("$baseDate {$att->clock_out}") : null;

                    $totalMinutes = 0;
                    $breakMinutes = $att->break_minutes ?? 0;
                    $workedMinutes = 0;
                    $nightMinutes = 0;

                    // 時給履歴取得
                    $wage = WageHistory::where('user_id', $user->id)
                        ->where('effective_from', '<=', $att->date)
                        ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $att->date))
                        ->orderByDesc('effective_from')
                        ->first();
                    $hourlyWage = $wage->hourly_wage ?? $user->wage ?? 0;
                    $lastHourlyWage = $hourlyWage;

                    $regularMinutes = 480; // 8h

                    if ($att->is_paid_leave) {
                        $regularWorkedMinutes = $regularMinutes;
                        $overtimeMinutes = 0;
                        $nightMinutes = 0;
                        $regularPay = ($regularWorkedMinutes / 60) * $hourlyWage;
                        $nightPay = 0;
                        $overtimePay = 0;
                        $workedMinutes = 0;
                    } elseif ($clockIn && $clockOut) {
                        if ($clockOut->lessThanOrEqualTo($clockIn)) {
                            $clockOut->addDay();
                        }

                        $totalMinutes = $clockIn->diffInMinutes($clockOut);
                        $workedMinutes = max(0, $totalMinutes - $breakMinutes);

                        $nightStart = $clockIn->copy()->setTime(22, 0);
                        $nightEnd   = $clockIn->copy()->addDay()->setTime(5, 0);
                        $overlapStart = $clockIn->max($nightStart);
                        $overlapEnd   = $clockOut->min($nightEnd);
                        $nightMinutes = $overlapStart->lt($overlapEnd)
                            ? $overlapStart->diffInMinutes($overlapEnd)
                            : 0;

                        $nightRatio = $nightMinutes / max($totalMinutes, 1);
                        $nightMinutes -= round($breakMinutes * $nightRatio);

                        $overtimeMinutes = max(0, $workedMinutes - $regularMinutes);
                        $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

                        $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
                        $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
                        $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

                    } else {
                        $regularWorkedMinutes = 0;
                        $overtimeMinutes = 0;
                        $regularPay = 0;
                        $nightPay = 0;
                        $overtimePay = 0;
                    }

                    $totalPay += $regularPay + $nightPay + $overtimePay;
                    $totalHours += $workedMinutes / 60;
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
     * 給与一覧
     */
    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $monthFilter = $request->month ?? null;

        $query = Attendance::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId));

        if ($monthFilter) {
            $query->where('date', 'like', $monthFilter . '%');
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $attendances = $query->get();

        // 有給シフト
        $paidLeavesQuery = Shift::with('user')
            ->where('is_paid_leave', 1)
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId));

        if ($monthFilter) {
            $paidLeavesQuery->where('shift_date', 'like', $monthFilter . '%');
        }
        if ($request->user_id) {
            $paidLeavesQuery->where('user_id', $request->user_id);
        }

        $paidLeaves = $paidLeavesQuery->get();

        foreach ($paidLeaves as $shift) {
            if ($attendances->where('user_id', $shift->user_id)->where('date', $shift->shift_date)->count()) {
                continue;
            }

            $hourlyWage = $shift->user->wage ?? 0;

            $attendances->push((object)[
                'user' => $shift->user,
                'date' => $shift->shift_date,
                'clock_in_for_view' => '-',
                'clock_out_for_view' => '-',
                'hours' => 0,
                'break_minutes' => 0,
                'is_paid_leave' => 1,
                'effective_wage' => $hourlyWage,
                'pay' => $hourlyWage * 8,
                'is_paid_leave_for_view' => '有給',
            ]);
        }

        foreach ($attendances as $attendance) {
            if (!empty($attendance->clock_in_for_view) && $attendance->clock_in_for_view != '-') {
                $baseDate = Carbon::parse($attendance->date)->format('Y-m-d');

                $clockIn  = Carbon::parse("$baseDate {$attendance->clock_in}");
                $clockOut = Carbon::parse("$baseDate {$attendance->clock_out}");

                if ($clockOut->lessThanOrEqualTo($clockIn)) {
                    $clockOut->addDay();
                }

                $attendance->clock_in_for_view  = $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '-';
                $attendance->clock_out_for_view = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '-';

                $totalMinutes = $clockIn->diffInMinutes($clockOut);
                $breakMinutes = $attendance->break_minutes ?? 0;
                $workedMinutes = max(0, $totalMinutes - $breakMinutes);

                $nightStart = $clockIn->copy()->setTime(22, 0);
                $nightEnd   = $clockIn->copy()->addDay()->setTime(5, 0);

                $overlapStart = $clockIn->max($nightStart);
                $overlapEnd   = $clockOut->min($nightEnd);

                $nightMinutes = $overlapStart->lt($overlapEnd)
                    ? $overlapStart->diffInMinutes($overlapEnd)
                    : 0;

                $nightRatio = $nightMinutes / max($totalMinutes, 1);
                $nightMinutes -= round($breakMinutes * $nightRatio);

                $month = Carbon::parse($attendance->date)->format('Y-m-01');
                $payroll = Payroll::where('user_id', $attendance->user->id)
                    ->where('month', $month)
                    ->first();

                $wage = WageHistory::where('user_id', $attendance->user->id)
                    ->where('effective_from', '<=', $attendance->date)
                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $attendance->date))
                    ->orderByDesc('effective_from')
                    ->first();

                $hourlyWage = $payroll->hourly_wage ?? ($wage->hourly_wage ?? $attendance->user->wage ?? 0);

                $regularMinutes = 480;
                $overtimeMinutes      = max(0, $workedMinutes - $regularMinutes);
                $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

                $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
                $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
                $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

                $attendance->pay = $attendance->is_paid_leave
                    ? $hourlyWage * 8
                    : round($regularPay + $nightPay + $overtimePay);

                $attendance->hours = round($workedMinutes / 60, 2);
                $attendance->effective_wage = $hourlyWage;
                $attendance->is_paid_leave_for_view = $attendance->is_paid_leave ? '有給' : '-';
            }
        }

        $users = User::where('company_id', $companyId)->get();
        $totalPaySum = $attendances->sum('pay');

        return view('company.payrolls', compact('attendances', 'users', 'company', 'totalPaySum'));
    }

    /**
     * CSV出力（ローカル版）
     */
    public function payrollsCsv(Company $company)
    {
        $attendances = Attendance::with(['user', 'shiftOfDay'])
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
            '遅刻','早退'
        ];

        foreach ($attendances as $a) {
            $baseDate = Carbon::parse($a->date)->format('Y-m-d');

            $clockIn  = $a->clock_in instanceof Carbon ? $a->clock_in : Carbon::parse($a->clock_in);
            $clockOut = $a->clock_out instanceof Carbon ? $a->clock_out : Carbon::parse($a->clock_out);

            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }

            $totalMinutes = $a->getTotalWorkMinutes();
            $workedHours = round($totalMinutes / 60, 2);

            $hourlyWage = $a->effective_wage;
            $pay = $a->pay;

            $lateMinutes = $a->late_minutes;
            $earlyMinutes = $a->early_leave_minutes;

            $csvData[] = [
                $a->user->name,
                $baseDate,
                $clockIn->format('H:i'),
                $clockOut->format('H:i'),
                $workedHours,
                round(($a->break_minutes ?? 0) / 60, 2),
                $hourlyWage,
                $pay,
                $lateMinutes > 0 ? gmdate('H:i', $lateMinutes * 60) : '',
                $earlyMinutes > 0 ? gmdate('H:i', $earlyMinutes * 60) : '',
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

        return redirect()
            ->route('company.payrolls', $company->id)
            ->with('success', '給与データを再計算しました。');
    }
}
