<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\User;
use App\Models\Company;
use App\Models\Shift;
use Carbon\Carbon;

class UserDashboardController extends Controller
{
    /**
     * 従業員ダッシュボード
     */
public function index(Request $request, Company $company, User $employee)
{
    if ($employee->company_id !== $company->id) abort(404);

    // ▼ 選択月（デフォルト今月）
    $selectedMonth = $request->input('month', Carbon::now()->format('Y-m'));
    $selected = Carbon::parse($selectedMonth . '-01');

    // ▼ グラフ範囲（12ヶ月前〜6ヶ月後）
    $start = Carbon::now()->subMonths(11)->startOfMonth();
    $end   = Carbon::now()->addMonths(6)->endOfMonth();

    $months = [];
    $workHours = [];
    $overTimes = [];
    $paidLeaveCounts = [];
    $salaryList = [];

    // ---------------------------------------------
    // ▼ 勤怠データ（広範囲で一括取得）
    // ---------------------------------------------
    $attData = Attendance::where('user_id', $employee->id)
        ->whereBetween('date', [$start, $end])
        ->orderBy('date')
        ->get()
        ->groupBy(fn($a) => Carbon::parse($a->date)->format('Y-m'));

    // ▼ 有休データ（Shiftから）
    $shiftData = Shift::where('user_id', $employee->id)
        ->whereBetween('shift_date', [$start, $end])
        ->get()
        ->groupBy(fn($s) => Carbon::parse($s->shift_date)->format('Y-m'));

    // =============================================
    // ▼ グラフ用計算（Attendance + 有休だけShift）
    // =============================================
    $period = new \DatePeriod(
        $start,
        new \DateInterval('P1M'),
        $end->copy()->addMonth()
    );

    foreach ($period as $month) {

        $ym = $month->format('Y-m');
        $months[] = $month->format('Y/m');

        $att = $attData->get($ym) ?? collect();
        $shifts = $shiftData->get($ym) ?? collect();

        $totalWork = 0;
        $totalOver = 0;
        $paidLeave = 0;

        // 有休は Shift からのみ
        foreach ($shifts as $s) {
            if ($s->is_paid_leave) $paidLeave++;
        }

        // 勤怠の実働時間
        foreach ($att as $a) {

            if (!$a->clock_in || !$a->clock_out) continue;

            $diff = $this->calcWorkMinutes(
                $a->date,
                $a->clock_in,
                $a->clock_out,
                $a->break_minutes
            );

            if ($diff > 0) {
                $totalWork += $diff;

                if ($diff > 480) {
                    $totalOver += $diff - 480;
                }
            }
        }

        $workHours[]       = round($totalWork / 60, 1);
        $overTimes[]       = round($totalOver / 60, 1);
        $paidLeaveCounts[] = $paidLeave;

        $payroll = Payroll::where('user_id', $employee->id)
            ->where('month', $month->format('Y-m-01'))
            ->first();

        $salaryList[] = $payroll?->total_pay ?? 0;
    }

    // =============================================
    // ▼ サマリー（Attendance + Shift only for 有休）
    // =============================================
    $monthAtt = Attendance::where('user_id', $employee->id)
        ->whereYear('date', $selected->year)
        ->whereMonth('date', $selected->month)
        ->get();

    $monthShifts = Shift::where('user_id', $employee->id)
        ->whereYear('shift_date', $selected->year)
        ->whereMonth('shift_date', $selected->month)
        ->get();

    $sumWork = 0;
    $sumOver = 0;
    $paidLeave = 0;
    $workCount = 0;

    // 有休は Shift
    foreach ($monthShifts as $s) {
        if ($s->is_paid_leave) $paidLeave++;
    }

    // 実働時間は Attendance
    foreach ($monthAtt as $a) {

        if (!$a->clock_in || !$a->clock_out) continue;

        $diff = $this->calcWorkMinutes(
            $a->date,
            $a->clock_in,
            $a->clock_out,
            $a->break_minutes
        );

        if ($diff > 0) {
            $sumWork += $diff;
            $workCount++;

            if ($diff > 480) {
                $sumOver += ($diff - 480);
            }
        }
    }

    $summary = [
        'work_hours'     => round($sumWork / 60, 1),
        'overtime'       => round($sumOver / 60, 1),
        'paid_leave'     => $paidLeave,
        'work_count'     => $workCount,
        'avg_work_hours' => $workCount ? round(($sumWork / 60) / $workCount, 1) : 0,
    ];

    return view('company.user.dashboard', compact(
        'company',
        'employee',
        'months',
        'workHours',
        'overTimes',
        'paidLeaveCounts',
        'salaryList',
        'summary',
        'selectedMonth'
    ));
}





    /**
     * 時間計算（日跨ぎ・29:00対応）
     */
    private function calcWorkMinutes($date, $startTime, $endTime, $break = 0)
    {
        if ($startTime === null || $endTime === null) {
            return 0;
        }

        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        } else {
            $date = substr((string)$date, 0, 10);
        }

        $start = $this->parseOverflowTime($date, $startTime);
        $end   = $this->parseOverflowTime($date, $endTime);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $minutes = $start->diffInMinutes($end) - ($break ?? 0);

        return max(0, $minutes);
    }


    /**
     * 29:00 → Carbon
     */
    private function parseOverflowTime($date, $time)
    {
        $parts = explode(':', $time);

        $h = (int)$parts[0];
        $m = (int)($parts[1] ?? 0);
        $s = (int)($parts[2] ?? 0);

        $dt = Carbon::parse($date)->setTime(0, 0, 0);

        if ($h >= 24) {
            $h -= 24;
            $dt->addDay();
        }

        return $dt->setTime($h, $m, $s);
    }
}
