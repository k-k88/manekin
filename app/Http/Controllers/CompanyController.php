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
    // ======================
    // ✅ ダッシュボード表示
    // ======================
    public function dashboard(Company $company)
    {
        $user = Auth::user();

        // アクセス制限：他社のデータは閲覧不可
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // ✅ 今日の出勤数
        $today_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereDate('date', $today)->count();

        // ✅ 登録社員数
        $employee_count = User::where('company_id', $company->id)->count();

        // ✅ 店舗数
        $store_count = Store::where('company_id', $company->id)->count();

        // ✅ 今月の出勤数
        $monthly_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereBetween('date', [$startOfMonth, $endOfMonth])->count();

        // ✅ 現在出勤中の社員数（退勤していない人）
        $active_employee_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereNull('clock_out')->count();

        // ✅ 最近の出勤ログ（最新10件・日付付き）
        $recent_attendances = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
            ->with('user')
            ->whereNotNull('clock_in')
            ->orderBy('clock_in', 'desc')
            ->take(10)
            ->get();

        // ✅ Viewへ渡す
        return view('company.dashboard', compact(
            'company',
            'user',
            'today_attendance_count',
            'employee_count',
            'store_count',
            'monthly_attendance_count',
            'active_employee_count',
            'recent_attendances'
        ));
    }

    // 👥 社員一覧
    public function employees($companyId)
    {
        $company = Company::findOrFail($companyId);
        $employees = User::where('company_id', $companyId)->get();

        return view('company.employees', compact('company', 'employees'));
    }

    // 🕒 勤怠一覧（月別・社員別フィルター付き）
    public function attendances(Request $request, Company $company)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        $users = $company->users()->get();

        $attendances = Attendance::whereHas('user', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->when($userId, fn($q) => $q->where('user_id', $userId))
        ->when($month, fn($q) => $q->where('date', 'like', $month . '%'))
        ->with('user')
        ->orderBy('date', 'desc')
        ->get();

        return view('company.attendances', compact('company', 'attendances', 'users'));
    }

    // 💰 勤怠データから給与を生成
    public function generatePayroll($companyId)
    {
        $users = User::where('company_id', $companyId)->get();

        DB::transaction(function() use ($users) {
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)->get();

                foreach ($attendances as $attendance) {
                    if (!$attendance->clock_in || !$attendance->clock_out) continue;

                    $hours = (strtotime($attendance->clock_out) - strtotime($attendance->clock_in)) / 3600;
                    $hourlyWage = 1000; // 仮の時給
                    $totalPay = $hours * $hourlyWage;

                    Payroll::updateOrCreate(
                        [
                           'user_id' => $user->id, 
                           'month' => date('Y-m-01', strtotime($attendance->date)) // ← 修正
                        ],
                        [
                           'hourly_wage' => $hourlyWage,
                           'total_hours' => $hours,
                           'total_pay' => $totalPay,
                        ]

                    );
                }
            }
        });

        return redirect()->route('company.payrolls', ['id' => $companyId])
                         ->with('success', '給与データを生成しました。');
    }

    // 💵 給与一覧（月・社員フィルター付き）
    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);

        $query = Payroll::whereHas('user', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });

        if ($request->month) {
            $month = Carbon::parse($request->month);
            $query->whereMonth('month', $month->month)
                  ->whereYear('month', $month->year);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $payrolls = $query->with('user')->orderBy('month', 'desc')->get();
        $users = User::where('company_id', $companyId)->get();

        return view('company.payrolls', compact('payrolls', 'users', 'company'));
    }

    // 👤 社員登録フォーム表示
    public function createEmployee($companyId)
    {
        $company = Company::findOrFail($companyId);
        $stores = Store::where('company_id', $companyId)->get(); // ✅ ← これが大事！

        return view('company.employees_create', compact('company', 'stores'));
    }

    // 👤 社員登録処理
    public function storeEmployee(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|in:employee,manager,admin',
            'store_id' => 'required|exists:stores,id',
            'hire_date' => 'required|date',
        ]);

        $validated['company_id'] = $companyId;
        $validated['password'] = bcrypt($validated['password']);
        $validated['status'] = 'active';

        User::create($validated);

        return redirect()->route('company.employees', $companyId)
                         ->with('success', '社員を登録しました。');
    }

    // ======================
    // 🔔 最近の出退勤ログ部分ビュー
    // ======================
   public function recentLogs(Company $company, Request $request)
{
    $date = $request->get('date', Carbon::today()->format('Y-m-d'));

    $recent_attendances = Attendance::whereHas('user', function ($q) use ($company) {
        $q->where('company_id', $company->id);
    })
        ->whereDate('date', $date)
        ->with('user')
        ->orderByDesc('updated_at')
        ->take(10)
        ->get();

    return view('company.partials.recent_logs', compact('recent_attendances'))->render();
}

}