<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\Company;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * 指定月の給与を計算して登録
     */
    public function calculatePayroll(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate   = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $users = User::all();

        foreach ($users as $user) {
            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $totalHours = 0;
            foreach ($attendances as $a) {
                if ($a->clock_in && $a->clock_out) {
                    $in  = Carbon::parse($a->clock_in);
                    $out = Carbon::parse($a->clock_out);
                    $totalHours += $out->diffInMinutes($in) / 60;
                }
            }

            $overtimeHours = max(0, $totalHours - 160);
            $hourlyWage   = $user->hourly_wage ?? 1200;
            $baseSalary   = $user->base_salary ?? 0;
            $overtimePay  = $overtimeHours * $hourlyWage * 1.25;
            $totalPay     = $baseSalary + ($totalHours * $hourlyWage) + $overtimePay;

            Payroll::updateOrCreate(
                ['user_id' => $user->id, 'month' => $month],
                [
                    'base_salary'    => $baseSalary,
                    'hourly_wage'    => $hourlyWage,
                    'worked_hours'   => $totalHours,
                    'overtime_hours' => $overtimeHours,
                    'overtime_pay'   => $overtimePay,
                    'bonus'          => 0,
                    'deductions'     => 0,
                    'total_pay'      => $totalPay,
                    'payment_date'   => Carbon::now(),
                ]
            );
        }

        return response()->json([
            'message' => "給与を集計しました",
            'month'   => $month
        ]);
    }

    /**
     * 給与一覧（全体）
     */
    public function index()
    {
        $payrolls = Payroll::with('user')->orderBy('month', 'desc')->get();
        return view('payroll.index', compact('payrolls'));
    }

    /**
     * 給与一覧（会社別・フィルター付き）
     */
    public function payrolls(Request $request, $id)
{
    $company = Company::findOrFail($id);

    $query = Payroll::whereHas('user', fn($q) => $q->where('company_id', $id));

    if ($request->month) {
        $query->whereMonth('month', Carbon::parse($request->month)->month)
              ->whereYear('month', Carbon::parse($request->month)->year);
    }

    if ($request->user_id) {
        $query->where('user_id', $request->user_id);
    }

    $payrolls = $query->with('user')->orderBy('month', 'desc')->get();

    // ここで会社所属社員を取得
    $users = User::where('company_id', $id)->get();

    return view('company.payrolls', compact('payrolls', 'users'));
}

}
