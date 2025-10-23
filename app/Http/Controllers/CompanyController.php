<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\Store;
use Carbon\Carbon;

class CompanyController extends Controller
{
    public function dashboard(Company $company)
    {
        $user = Auth::user();

        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $today_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereDate('date', $today)->count();

        $employee_count = User::where('company_id', $company->id)->count();
        $store_count = Store::where('company_id', $company->id)->count();

        $monthly_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereBetween('date', [$startOfMonth, $endOfMonth])->count();

        $active_employee_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereNull('clock_out')->count();

        return view('company.dashboard', compact(
            'company',
            'user',
            'today_attendance_count',
            'employee_count',
            'store_count',
            'monthly_attendance_count',
            'active_employee_count'
        ));
    }

    public function employees($companyId)
    {
        $company = Company::findOrFail($companyId);
        $employees = User::where('company_id', $companyId)->get();

        return view('company.employees', compact('company', 'employees'));
    }

    public function attendances(Request $request, Company $company)
{
    $month = $request->input('month', now()->format('Y-m'));
    $userId = $request->input('user_id');

    // 会社の社員一覧
    $users = $company->users()->get();

    // 勤怠データ取得
    $attendances = \App\Models\Attendance::whereHas('user', function ($query) use ($company) {
        $query->where('company_id', $company->id);
    })
    ->when($userId, fn($q) => $q->where('user_id', $userId))
    ->when($month, fn($q) => $q->where('date', 'like', $month . '%'))
    ->with('user')
    ->orderBy('date', 'desc')
    ->get();

    return view('company.attendances', compact('company', 'attendances', 'users'));
}



      // 勤怠データから給与を生成
    public function generatePayroll($companyId)
    {
        $users = User::where('company_id', $companyId)->get();
 
        DB::transaction(function() use ($users) {
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)->get();
 
                foreach ($attendances as $attendance) {
                    $hours = (strtotime($attendance->clock_out) - strtotime($attendance->clock_in)) / 3600;
                    $hourlyWage = 1000; // 仮の時給
                    $totalPay = $hours * $hourlyWage;
 
                    Payroll::updateOrCreate(
                        ['user_id' => $user->id, 'month' => $attendance->date],
                        [
                            'hourly_wage' => $hourlyWage,
                            'total_hours' => $hours,
                            'total_pay' => $totalPay
                        ]
                    );
                }
            }
        });
 
        return redirect()->route('company.payrolls', ['id' => $companyId])
                         ->with('success', '給与データを生成しました');
    }
 

    // 給与一覧（社員別・月別フィルター付き）
    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);

        $query = Payroll::whereHas('user', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });

        // 月フィルター
        if ($request->month) {
            $month = Carbon::parse($request->month);
            $query->whereMonth('month', $month->month)
                  ->whereYear('month', $month->year);
        }

        // 社員フィルター
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $payrolls = $query->with('user')->orderBy('month', 'desc')->get();
        $users = User::where('company_id', $companyId)->get();

        return view('company.payrolls', compact('payrolls', 'users'));
    }

    public function createEmployee($companyId)
{
    $company = Company::findOrFail($companyId);

    // 所属チェック（管理者のみ）
    $user = Auth::user();
    if ($user->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    return view('company.employees_create', compact('company'));
}

public function storeEmployee(Request $request, $companyId)
{
    $company = Company::findOrFail($companyId);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
        'phone' => 'nullable|string|max:20',
        'role' => 'required|string|in:employee,manager,admin',
    ]);

    $validated['company_id'] = $companyId;
    $validated['password'] = bcrypt($validated['password']);
    $validated['status'] = 'active';

    User::create($validated);

    return redirect()->route('company.employees', $companyId)
                     ->with('success', '社員を登録しました。');
}

}
