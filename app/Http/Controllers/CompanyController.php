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
use Barryvdh\DomPDF\Facade\Pdf; // ← 追加

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

    public function destroyAttendance(Company $company, Attendance $attendance)
{
    // 会社チェック：他社データの削除防止
    if ($attendance->user->company_id !== $company->id) {
        abort(403, '他社の勤怠データは削除できません。');
    }

    $attendance->delete();

    return redirect()->route('company.attendances', $company->id)
                     ->with('success', '勤怠データを削除しました。');
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

    // Attendance から日別データ取得
    $query = Attendance::with('user')
        ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
        ->whereNotNull('clock_in')
        ->whereNotNull('clock_out');

    // 月フィルター
    if ($request->month) {
        $query->where('date', 'like', $request->month . '%');
    }

    // 社員フィルター
    if ($request->user_id) {
        $query->where('user_id', $request->user_id);
    }

    $attendances = $query->orderBy('date', 'desc')->get();

    $hourlyWage = 1000; // 時給固定（必要に応じて変更可能）

    $users = User::where('company_id', $companyId)->get();

    return view('company.payrolls', compact('attendances', 'users', 'company', 'hourlyWage'));
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
public function editEmployee(Company $company, User $employee)
{
    $stores = Store::where('company_id', $company->id)->get();

    return view('company.employees_edit', compact('company', 'employee', 'stores'));
}

public function updateEmployee(Request $request, Company $company, User $employee)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => 'nullable|string|max:20',
        'store_id' => 'required|exists:stores,id',
        'hire_date' => 'required|date',
    ]);

    $employee->update($validated);

    return redirect()->route('company.employees', $company)
        ->with('success', '社員情報を更新しました。');
}


public function payrollsPdf(Request $request, $companyId)
{
    $company = Company::findOrFail($companyId);

    // 月指定（未指定なら今月）
    $month = $request->month ?? now()->format('Y-m');

    // Attendance から日別データ取得
    $attendances = Attendance::with('user')
        ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
        ->whereNotNull('clock_in')
        ->whereNotNull('clock_out')
        ->where('date', 'like', $month . '%')
        ->orderBy('date', 'asc')
        ->get();

    $hourlyWage = 1000; // 時給固定（必要に応じて変更可能）

    return Pdf::loadView('company.payrolls_pdf', compact('attendances', 'company', 'month', 'hourlyWage'))
              ->setPaper('A4', 'landscape') // 横向きにする場合
              ->download("給与一覧_{$month}.pdf");
}

 // 👤 社員削除処理
public function deleteEmployee($companyId, $employeeId)
{
    $company = Company::findOrFail($companyId);
    $employee = User::findOrFail($employeeId);

    // 会社が一致しない場合はアクセス拒否
    if ($employee->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    // 削除実行
    $employee->delete();

    // ✅ 削除後は社員一覧にリダイレクト
    return redirect()->route('company.employees', $company->id)
                     ->with('success', '社員を削除しました。');
}


}