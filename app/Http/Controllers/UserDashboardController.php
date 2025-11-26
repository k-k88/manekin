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
    public function index(Company $company, User $employee)
    {
        if ($employee->company_id !== $company->id) abort(404);

        $months = [];
        $workHours = [];
        $overTimes = [];
        $paidLeaveCounts = [];
        $salaryList = [];

        // ------------------------------------------------------------
        // 直近12ヶ月〜未来6ヶ月（計18ヶ月）
        // ------------------------------------------------------------
        $start = Carbon::now()->subMonths(11);
        $end   = Carbon::now()->addMonths(6);

        $allAttMonths = Attendance::where('user_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('date')
            ->get()
            ->groupBy(fn($att) => Carbon::parse($att->date)->format('Y-m'));

        // 出勤なし
        if ($allAttMonths->isEmpty()) {
            $summary = [
                'work_hours' => 0,
                'overtime' => 0,
                'paid_leave' => 0,
                'work_count' => 0,
                'avg_work_hours' => 0,
            ];

            return view('company.user.dashboard', compact(
                'company', 'employee',
                'months', 'workHours', 'overTimes', 'paidLeaveCounts', 'salaryList',
                'summary'
            ));
        }

        // ------------------------------------------------------------
        // 月ごとの集計（有給完全対応版）
        // ------------------------------------------------------------
        foreach ($allAttMonths as $ym => $monthAtt) {

            $target = Carbon::parse($ym . "-01");
            $months[] = $target->format('Y/m');

            $shifts = Shift::where('user_id', $employee->id)
                ->whereYear('shift_date', $target->year)
                ->whereMonth('shift_date', $target->month)
                ->get();

            $totalMinutes = 0;
            $overtimeMinutes = 0;
            $paidLeaveDays = 0;

            // シフトベースで完全に集計
            foreach ($shifts as $shift) {

                // 勤怠（attendance）があるか？
                $att = $monthAtt->firstWhere('date', $shift->shift_date);

                // ① 有給なら即カウントして終了
                if ($shift->is_paid_leave) {
                    $paidLeaveDays++;
                    continue;
                }

                // ② 勤怠がある → 勤怠優先
                if ($att && $att->clock_in && $att->clock_out) {
                    $diff = $this->calcWorkMinutes(
                        $att->date,
                        $att->clock_in,
                        $att->clock_out,
                        $att->break_minutes
                    );

                    if ($diff > 0) {
                        $totalMinutes += $diff;
                        if ($diff > 480) $overtimeMinutes += ($diff - 480);
                    }
                    continue;
                }

                // ③ 勤怠なし → シフトから時間計算
                if (!$shift->is_day_off && $shift->start_time && $shift->end_time) {
                    $diff = $this->calcWorkMinutes(
                        $shift->shift_date,
                        $shift->start_time,
                        $shift->end_time,
                        0
                    );

                    if ($diff > 0) {
                        $totalMinutes += $diff;
                        if ($diff > 480) $overtimeMinutes += ($diff - 480);
                    }
                }
            }

            $workHours[] = round($totalMinutes / 60, 1);
            $overTimes[] = round($overtimeMinutes / 60, 1);
            $paidLeaveCounts[] = $paidLeaveDays;

            // 給与
            $payroll = Payroll::where('user_id', $employee->id)
                ->where('month', $target->format('Y-m-01'))
                ->first();

            $salaryList[] = $payroll?->total_pay ?? 0;
        }

        // ------------------------------------------------------------
        // サマリー：最新の出勤がある月
        // ------------------------------------------------------------
        $latestMonthKey = $allAttMonths->keys()->sort()->last();
        $latestMonth = Carbon::parse($latestMonthKey . '-01');

        $monthAtt = $allAttMonths->get($latestMonthKey) ?? collect();

        $sumWork = 0;
        $sumOver = 0;
        $paidLeaveCurrent = 0;

        $monthShifts = Shift::where('user_id', $employee->id)
            ->whereYear('shift_date', $latestMonth->year)
            ->whereMonth('shift_date', $latestMonth->month)
            ->get();

        foreach ($monthShifts as $shift) {

            $att = $monthAtt->firstWhere('date', $shift->shift_date);

            if ($shift->is_paid_leave) {
                $paidLeaveCurrent++;
                continue;
            }

            if ($att && $att->clock_in && $att->clock_out) {

                $diff = $this->calcWorkMinutes(
                    $att->date,
                    $att->clock_in,
                    $att->clock_out,
                    $att->break_minutes
                );

                if ($diff > 0) {
                    $sumWork += $diff;
                    if ($diff > 480) $sumOver += ($diff - 480);
                }
                continue;
            }

            if (!$shift->is_day_off && $shift->start_time && $shift->end_time) {
                $diff = $this->calcWorkMinutes(
                    $shift->shift_date,
                    $shift->start_time,
                    $shift->end_time
                );

                if ($diff > 0) {
                    $sumWork += $diff;
                    if ($diff > 480) $sumOver += ($diff - 480);
                }
            }
        }

        $workCount = $monthAtt->filter(fn($a) => $a->clock_in && $a->clock_out)->count();
        $avgHours = $workCount ? round(($sumWork / 60) / $workCount, 1) : 0;

        $summary = [
            'work_hours' => round($sumWork / 60, 1),
            'overtime'   => round($sumOver / 60, 1),
            'paid_leave' => $paidLeaveCurrent,
            'work_count' => $workCount,
            'avg_work_hours' => $avgHours,
        ];

        return view('company.user.dashboard', compact(
            'company',
            'employee',
            'months',
            'workHours',
            'overTimes',
            'paidLeaveCounts',
            'salaryList',
            'summary'
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
