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
use App\Models\WageHistory;

class CompanyController extends Controller
{
    // ======================
    // ✅ ダッシュボード表示
    // ======================
    public function dashboard(Company $company)
    {
        $user = Auth::user();
        if ($user->company_id !== $company->id) abort(403, 'アクセス権がありません');

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
            'company', 'user', 'today_attendance_count', 'employee_count',
            'store_count', 'monthly_attendance_count', 'active_employee_count', 'recent_attendances'
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

        $attendances = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($month, fn($q) => $q->where('date', 'like', $month . '%'))
            ->with('user')
            ->orderBy('date', 'desc')
            ->get();

        return view('company.attendances', compact('company', 'attendances', 'users'));
    }

    // 編集フォーム表示
    public function editAttendance($companyId, Attendance $attendance)
    {
        $company = Company::findOrFail($companyId);
        return view('company.editAttendance', compact('company', 'attendance'));
    }

    // 更新処理
    public function updateAttendance(Request $request, Company $company, Attendance $attendance)
    {
        if ($attendance->user->company_id !== $company->id) abort(403, '他社の勤怠は更新できません');

        $request->validate([
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'required|date_format:H:i|after:clock_in',
        ]);

        $wageHistory = WageHistory::where('user_id', $attendance->user_id)
            ->where('effective_from', '<=', $attendance->date)
            ->orderByDesc('effective_from')
            ->first();

        $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : $attendance->user->hourly_wage;

        $attendance->update([
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
            'hourly_wage' => $hourlyWage,
        ]);

        return redirect()->route('company.attendances', ['company' => $company->id])
            ->with('success', '勤怠を更新しました。');
    }

    // 勤怠削除
    public function destroyAttendance(Company $company, Attendance $attendance)
    {
        if ($attendance->user->company_id !== $company->id) abort(403, '他社の勤怠データは削除できません');

        // Payroll を先に削除
        Payroll::where('attendance_id', $attendance->id)->delete();
        $attendance->delete();

        return redirect()->route('company.attendances', $company->id)
            ->with('success', '勤怠データを削除しました。');
    }

    // 勤怠追加フォーム
    public function createAttendance(Company $company)
    {
        $users = $company->users()->get();
        return view('company.attendances_create', compact('company', 'users'));
    }

    // 勤怠保存
    public function storeAttendance(Request $request, Company $company)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i|after:clock_in',
        ]);

        $user = User::findOrFail($validated['user_id']);

        // 時給取得
        $wageHistory = WageHistory::where('user_id', $user->id)
            ->where('effective_from', '<=', $validated['date'])
            ->orderByDesc('effective_from')
            ->first();

        $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : $user->hourly_wage;

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'date' => $validated['date'],
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'],
            'hourly_wage' => $hourlyWage,
        ]);

        // 勤務時間・給与計算
        $hours = 0;
        $totalPay = 0;
        if ($attendance->clock_in && $attendance->clock_out) {
            $hours = (strtotime($attendance->clock_out) - strtotime($attendance->clock_in)) / 3600;
            $totalPay = $hours * $hourlyWage;
        }

        Payroll::updateOrCreate(
            [
                'user_id' => $user->id,
                'month' => date('Y-m-01', strtotime($attendance->date)),
            ],
            [
                'attendance_id' => $attendance->id,
                'hourly_wage' => $hourlyWage,
                'total_hours' => $hours,
                'total_pay' => $totalPay,
                'company_id' => $company->id,
            ]
        );

        return redirect()->route('company.attendances', ['company' => $company->id])
            ->with('success', '勤怠を追加しました。');
    }

    // 💰 勤怠から給与生成
    public function generatePayroll($companyId)
    {
        $users = User::where('company_id', $companyId)->get();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $attendances = Attendance::where('user_id', $user->id)
                    ->whereNotNull('clock_in')
                    ->whereNotNull('clock_out')
                    ->get();

                foreach ($attendances as $attendance) {
                    $hours = (strtotime($attendance->clock_out) - strtotime($attendance->clock_in)) / 3600;
                    $hourlyWage = $attendance->hourly_wage ?? 1000;
                    $totalPay = $hours * $hourlyWage;

                    Payroll::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'month' => date('Y-m-01', strtotime($attendance->date)),
                        ],
                        [
                            'attendance_id' => $attendance->id,
                            'hourly_wage' => $hourlyWage,
                            'total_hours' => $hours,
                            'total_pay' => $totalPay,
                            'company_id' => $attendance->company_id,
                        ]
                    );
                }
            }
        });

        return redirect()->route('company.payrolls', ['company' => $companyId])
            ->with('success', '給与データを生成しました。');
    }

    // 💵 給与一覧
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
        $users = User::where('company_id', $companyId)->get();

        foreach ($attendances as $attendance) {
            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : $attendance->user->hourly_wage;
            $attendance->hourly_wage = $hourlyWage;

            if ($attendance->clock_in && $attendance->clock_out) {
                $hours = Carbon::parse($attendance->clock_in)->diffInMinutes($attendance->clock_out) / 60;
                $attendance->hours = $hours;
                $attendance->pay = $hours * $hourlyWage;
            } else {
                $attendance->hours = 0;
                $attendance->pay = 0;
            }
        }

        return view('company.payrolls', compact('attendances', 'users', 'company'));
    }

    // 👤 社員登録
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

        if (!empty($validated['phone'])) {
            $digits = preg_replace('/\D/', '', $validated['phone']);
            $validated['phone'] = match (strlen($digits)) {
                10 => preg_replace('/(\d{2,3})(\d{3,4})(\d{4})/', '$1-$2-$3', $digits),
                11 => preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $digits),
                default => $validated['phone'],
            };
        }

        $validated['company_id'] = $companyId;
        $validated['password'] = bcrypt($validated['password']);
        $validated['status'] = 'active';

        User::create($validated);

        return redirect()->route('company.employees', $companyId)
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

        if (!empty($validated['phone'])) {
            $digits = preg_replace('/\D/', '', $validated['phone']);
            $validated['phone'] = match (strlen($digits)) {
                10 => preg_replace('/(\d{2,3})(\d{3,4})(\d{4})/', '$1-$2-$3', $digits),
                11 => preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $digits),
                default => $validated['phone'],
            };
        }

        $employee->update($validated);

        return redirect()->route('company.employees', $company)
            ->with('success', '社員情報を更新しました。');
    }

    public function deleteEmployee($companyId, $employeeId)
    {
        $company = Company::findOrFail($companyId);
        $employee = User::findOrFail($employeeId);

        if ($employee->company_id !== $company->id) abort(403, 'アクセス権がありません');

        $employee->delete();

        return redirect()->route('company.employees', $company->id)
            ->with('success', '社員を削除しました。');
    }

    // 給与PDF
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

    // CSV出力
    public function payrollsCsv(Company $company)
    {
        $month = request('month', now()->format('Y-m'));
        $attendances = $company->attendances()
            ->whereYear('date', Carbon::parse($month)->year)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->get();

        $csvData = "社員名,日付,出勤,退勤,勤務時間,時給,給与\n";

        foreach ($attendances as $attendance) {
            if (!$attendance->clock_in || !$attendance->clock_out) continue;

            $wageHistory = WageHistory::where('user_id', $attendance->user_id)
                ->where('effective_from', '<=', $attendance->date)
                ->orderByDesc('effective_from')
                ->first();

            $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : $attendance->user->hourly_wage;
            $hours = Carbon::parse($attendance->clock_in)->diffInMinutes(Carbon::parse($attendance->clock_out)) / 60;
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
}
