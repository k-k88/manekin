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
use Barryvdh\DomPDF\Facade\Pdf;

class CompanyController extends Controller
{
    // ======================
    // ✅ ダッシュボード表示
    // ======================
    public function dashboard(Company $company)
    {
        $user = Auth::user();

        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $today_attendance_count = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereDate('date', $today)->count();

        $employee_count = User::where('company_id', $company->id)->count();
        $store_count = Store::where('company_id', $company->id)->count();

        $monthly_attendance_count = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])->count();

        $active_employee_count = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereNull('clock_out')->count();

        $recent_attendances = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->with('user')
            ->whereNotNull('clock_in')
            ->orderBy('clock_in', 'desc')
            ->take(10)
            ->get();

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

    // 🕒 勤怠一覧
    public function attendances(Request $request, Company $company)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');

        $users = $company->users()->get();

        $attendances = Attendance::whereHas('user', fn($query) => $query->where('company_id', $company->id))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($month, fn($q) => $q->where('date', 'like', $month . '%'))
            ->with('user')
            ->orderBy('date', 'desc')
            ->get();

        return view('company.attendances', compact('company', 'attendances', 'users'));
    }

    // ✅ 勤怠追加
    public function createAttendance(Company $company)
    {
        $users = $company->users()->get();
        return view('company.attendances_create', compact('company', 'users'));
    }

    public function storeAttendance(Request $request, Company $company)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i|after:clock_in',
        ]);

        Attendance::create([
            'user_id' => $validated['user_id'],
            'company_id' => $company->id,
            'date' => $validated['date'],
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'],
        ]);

        return redirect()->route('company.attendances', ['company' => $company->id])
                         ->with('success', '勤怠を追加しました。');
    }

    // 勤怠編集・更新・削除
    public function editAttendance($companyId, Attendance $attendance)
    {
        $company = Company::findOrFail($companyId);
        return view('company.editAttendance', compact('company', 'attendance'));
    }

    public function updateAttendance(Request $request, Company $company, Attendance $attendance)
    {
        if ($attendance->user->company_id !== $company->id) {
            abort(403, '他社の勤怠は更新できません');
        }

        $request->validate([
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'required|date_format:H:i|after:clock_in',
        ]);

        $attendance->update([
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
        ]);

        return redirect()->route('company.attendances', ['company' => $company->id])
                         ->with('success', '勤怠を更新しました。');
    }

    public function destroyAttendance(Company $company, Attendance $attendance)
    {
        if ($attendance->user->company_id !== $company->id) {
            abort(403, '他社の勤怠データは削除できません。');
        }

        $attendance->delete();

        return redirect()->route('company.attendances', ['company' => $company->id])
                         ->with('success', '勤怠データを削除しました。');
    }

    // 💰 給与生成・一覧・PDF・CSV
    public function generatePayroll($companyId)
    {
        $users = User::where('company_id', $companyId)->get();

        DB::transaction(function() use ($users) {
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)->get();

                foreach ($attendances as $attendance) {
                    if (!$attendance->clock_in || !$attendance->clock_out) continue;

                    $hours = (strtotime($attendance->clock_out) - strtotime($attendance->clock_in)) / 3600;
                    $hourlyWage = 1000;
                    $totalPay = $hours * $hourlyWage;

                    Payroll::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'month' => date('Y-m-01', strtotime($attendance->date))
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

        return redirect()->route('company.payrolls', ['company' => $companyId])
                         ->with('success', '給与データを生成しました。');
    }

    public function payrolls(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $query = Attendance::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out');

        if ($request->month) $query->where('date', 'like', $request->month . '%');
        if ($request->user_id) $query->where('user_id', $request->user_id);

        $attendances = $query->orderBy('date', 'desc')->get();
        $hourlyWage = 1000;
        $users = User::where('company_id', $companyId)->get();

        return view('company.payrolls', compact('attendances', 'users', 'company', 'hourlyWage'));
    }

    public function payrollsPdf(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $month = $request->month ?? now()->format('Y-m');

        $attendances = Attendance::with('user')
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->where('date', 'like', $month . '%')
            ->orderBy('date', 'asc')
            ->get();

        $hourlyWage = 1000;

        return Pdf::loadView('company.payrolls_pdf', compact('attendances', 'company', 'month', 'hourlyWage'))
                  ->setPaper('A4', 'landscape')
                  ->download("給与一覧_{$month}.pdf");
    }

    public function payrollsCsv(Company $company)
    {
        $month = request('month', now()->format('Y-m'));
        $attendances = $company->attendances()
                               ->whereYear('date', Carbon::parse($month)->year)
                               ->whereMonth('date', Carbon::parse($month)->month)
                               ->get();

        $hourlyWage = 1200;
        $csvData = "社員名,日付,出勤,退勤,勤務時間,時給,給与\n";

        foreach ($attendances as $attendance) {
            if (!$attendance->clock_in || !$attendance->clock_out) continue;

            $hours = Carbon::parse($attendance->clock_in)->diffInMinutes($attendance->clock_out) / 60;
            $pay = $hours * $hourlyWage;

            $csvData .= "{$attendance->user->name}," .
                        Carbon::parse($attendance->date)->format('Y/m/d') . "," .
                        Carbon::parse($attendance->clock_in)->format('H:i') . "," .
                        Carbon::parse($attendance->clock_out)->format('H:i') . "," .
                        number_format($hours, 2) . "," .
                        $hourlyWage . "," .
                        $pay . "\n";
        }

        $csvData = mb_convert_encoding($csvData, 'SJIS-win', 'UTF-8');
        $filename = $company->name . '-' . str_replace('-', '', $month) . '.csv';

        return response($csvData)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    // 👤 社員作成・編集・削除
    public function createEmployee($companyId)
    {
        $company = Company::findOrFail($companyId);
        $stores = Store::where('company_id', $companyId)->get();
        return view('company.employees_create', compact('company', 'stores'));
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
            'store_id' => 'required|exists:stores,id',
            'hire_date' => 'required|date',
        ]);

        $validated['company_id'] = $companyId;
        $validated['password'] = bcrypt($validated['password']);
        $validated['status'] = 'active';

        User::create($validated);

        return redirect()->route('company.employees', ['company' => $companyId])
                         ->with('success', '社員を登録しました。');
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

        return redirect()->route('company.employees', ['company' => $company->id])
                         ->with('success', '社員情報を更新しました。');
    }

    public function deleteEmployee($companyId, $employeeId)
    {
        $company = Company::findOrFail($companyId);
        $employee = User::findOrFail($employeeId);

        if ($employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $employee->delete();

        return redirect()->route('company.employees', ['company' => $company->id])
                         ->with('success', '社員を削除しました。');
    }

    // ==========================
    // 📋 最近の勤怠ログ
    // ==========================
    public function recentLogs(Company $company)
    {
        $recent_attendances = Attendance::whereHas('user', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->with('user')
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();

        return view('company.partials.recent_logs', compact('recent_attendances'))->render();
    }
}
