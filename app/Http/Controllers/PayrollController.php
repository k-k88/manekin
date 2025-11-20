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

                    // 時給履歴取得（なければユーザーの基本時給を使用）
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

                        // 深夜帯計算
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
     * 給与一覧表示
     */
    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $monthFilter = $request->month ?? null;

        // shift を必ず eager load
        $query = Attendance::with(['user', 'shift'])
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId));

        if ($monthFilter) {
            $query->where('date', 'like', $monthFilter . '%');
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $attendances = $query->get();

        /**
         * Attendance が無い有給シフトを追加
         */
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

            $exists = $attendances->first(function ($att) use ($shift) {
                return $att->user_id == $shift->user_id &&
                       $att->date == $shift->shift_date;
            });

            if ($exists) continue;

            $wageHistory = WageHistory::getWageForDate($shift->user_id, $shift->shift_date);
            $hourlyWage  = $wageHistory->hourly_wage ?? ($shift->user->wage ?? 0);

            $attendances->push((object)[
                'user' => $shift->user,
                'user_id' => $shift->user_id,
                'date' => $shift->shift_date,
                'clock_in' => null,
                'clock_out' => null,
                'clock_in_for_view' => '有休',
                'clock_out_for_view' => '有休',
                'hours' => 8.00,
                'break_minutes' => 0,
                'is_paid_leave' => 1,
                'effective_wage' => $hourlyWage,
                'pay' => $hourlyWage * 8,
                'is_paid_leave_for_view' => true,
                'shift' => $shift,
            ]);
        }

        /**
         * 出勤ありの給与計算
         */
        foreach ($attendances as $attendance) {

            $isPaidLeave = $attendance->shift?->is_paid_leave ?? false;
            $attendance->is_paid_leave_for_view = $isPaidLeave;

            if ($isPaidLeave) {
                $wageHistory = WageHistory::getWageForDate($attendance->user_id, $attendance->date);
                $hourlyWage  = $wageHistory->hourly_wage ?? ($attendance->user->wage ?? 0);

                $attendance->effective_wage = $hourlyWage;
                $attendance->clock_in_for_view  = '有休';
                $attendance->clock_out_for_view = '有休';
                $attendance->hours = 8.00;
                $attendance->pay = $hourlyWage * 8;
                continue;
            }

            // 出勤なし
            if (!$attendance->clock_in || !$attendance->clock_out) {
                $attendance->clock_in_for_view  = '-';
                $attendance->clock_out_for_view = '-';
                $attendance->hours = 0;
                $attendance->pay = 0;
                continue;
            }

            // 出勤あり
            $baseDate = Carbon::parse($attendance->date)->format('Y-m-d');

            $clockIn  = Carbon::parse("$baseDate {$attendance->clock_in}");
            $clockOut = Carbon::parse("$baseDate {$attendance->clock_out}");

            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $clockOut->addDay();
            }

            $attendance->clock_in_for_view  = Carbon::parse($attendance->clock_in)->format('H:i');
            $attendance->clock_out_for_view = Carbon::parse($attendance->clock_out)->format('H:i');

            $totalMinutes = $clockIn->diffInMinutes($clockOut);
            $breakMinutes = $attendance->break_minutes ?? 0;
            $workedMinutes = max(0, $totalMinutes - $breakMinutes);

            // 深夜帯
            $nightStart = $clockIn->copy()->setTime(22, 0);
            $nightEnd   = $clockIn->copy()->addDay()->setTime(5, 0);
            $overlapStart = $clockIn->max($nightStart);
            $overlapEnd   = $clockOut->min($nightEnd);
            $nightMinutes = $overlapStart->lt($overlapEnd)
                ? $overlapStart->diffInMinutes($overlapEnd)
                : 0;

            // 時給
            $wageHistory = WageHistory::getWageForDate($attendance->user_id, $attendance->date);
            $hourlyWage = $wageHistory->hourly_wage ?? ($attendance->user->wage ?? 0);
            $attendance->effective_wage = $hourlyWage;

            // 支給計算
            $attendance->hours = round($workedMinutes / 60, 2);

            $regularMinutes = 480;
            $overtimeMinutes = max(0, $workedMinutes - $regularMinutes);
            $regularWorkedMinutes = $workedMinutes - $overtimeMinutes;

            $regularPay  = ($regularWorkedMinutes / 60) * $hourlyWage;
            $nightPay    = ($nightMinutes / 60) * $hourlyWage * 1.25;
            $overtimePay = ($overtimeMinutes / 60) * $hourlyWage * 1.25;

            $attendance->pay = round($regularPay + $nightPay + $overtimePay);
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
            ->orderBy('date', 'desc')
            ->get();

        if ($attendances->isEmpty()) {
            return back()->with('error', '出力できる勤怠データがありません。');
        }

        $csvData = [];
        $csvData[] = ['社員名','日付','出勤時刻','退勤時刻','勤務時間(時間)','休憩時間(時間)','有給','時給(円)','支給額(円)'];

        foreach ($attendances as $a) {
            $baseDate = Carbon::parse($a->date)->format('Y-m-d');

            $clockIn  = $a->clock_in ? Carbon::parse("$baseDate {$a->clock_in}") : null;
            $clockOut = $a->clock_out ? Carbon::parse("$baseDate {$a->clock_out}") : null;

            $totalMinutes = $clockIn && $clockOut ? $clockIn->diffInMinutes($clockOut) : 0;
            $breakMinutes = $a->break_minutes ?? 0;
            $workedMinutes = max(0, $totalMinutes - $breakMinutes);

            $wage = WageHistory::where('user_id', $a->user_id)
                ->where('effective_from', '<=', $a->date)
                ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $a->date))
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $wage->hourly_wage ?? $a->user->wage ?? 0;

            $pay = $a->is_paid_leave ? 8 * $hourlyWage : round(($workedMinutes / 60) * $hourlyWage);

            $csvData[] = [
                $a->user->name,
                $baseDate,
                $a->clock_in ?? '-',
                $a->clock_out ?? '-',
                round($workedMinutes / 60, 2),
                round(($a->break_minutes ?? 0) / 60, 2),
                $a->is_paid_leave ? '有給' : '-',
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

        return redirect()
            ->route('company.payrolls', $company->id)
            ->with('success', '給与データを再計算しました。');
    }
}
