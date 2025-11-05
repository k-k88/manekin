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
    // 他社の勤怠を操作させない
    if ($attendance->user->company_id !== $company->id) {
        abort(403, '他社の勤怠は更新できません');
    }
 
    // 勤怠日が未来なら更新禁止
    if (\Carbon\Carbon::parse($attendance->date)->isFuture()) {
        return redirect()->back()
            ->withErrors(['date' => '未来日の勤怠は更新できません。']);
    }
 
    // 入退勤時刻を取得（text型で25時なども許可）
    $clockInStr = $request->clock_in;
    $clockOutStr = $request->clock_out;
 
    // 文字列を時間と分に分解
    [$inHour, $inMinute] = explode(':', $clockInStr);
    [$outHour, $outMinute] = explode(':', $clockOutStr);
 
    // 24時超過を補正
    $outHourInt = (int)$outHour;
    if ($outHourInt >= 24) {
        $outHourInt -= 24; // データベースには0-23で保存
        $outDayOffset = 1;  // 日跨ぎ
    } else {
        $outDayOffset = 0;
    }
 
    $clockIn = \Carbon\Carbon::createFromTime((int)$inHour, (int)$inMinute);
    $clockOut = \Carbon\Carbon::createFromTime((int)$outHourInt, (int)$outMinute)->addDays($outDayOffset);
 
    if ($clockOut->lessThanOrEqualTo($clockIn)) {
        return redirect()->back()->withErrors(['clock_out' => '退勤時刻は出勤時刻より後の時間を指定してください。']);
    }
 
    // 当日の有効な時給取得
    $wageHistory = WageHistory::where('user_id', $attendance->user_id)
        ->where('effective_from', '<=', $attendance->date)
        ->orderByDesc('effective_from')
        ->first();
 
    $hourlyWage = $wageHistory ? $wageHistory->hourly_wage : $attendance->user->hourly_wage;
 
    // 勤怠更新
    $attendance->update([
        'clock_in' => sprintf('%02d:%02d', $clockIn->hour, $clockIn->minute),
        'clock_out' => sprintf('%02d:%02d', $clockOut->hour, $clockOut->minute),
        'hourly_wage' => $hourlyWage,
    ]);
 
    return redirect()->route('company.attendances', ['company' => $company->id])
        ->with('success', '勤怠を更新しました。');
}
 
    // 💰 勤怠から給与生成（過去データを上書きしない版）
public function generatePayroll(Company $company)
{
    // 会社の社員を取得
    $users = $company->users;
 
    foreach ($users as $user) {
        // 勤怠を月ごとにまとめる
        $attendancesByMonth = Attendance::where('user_id', $user->id)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->get()
            ->groupBy(function ($att) {
                return Carbon::parse($att->date)->startOfMonth()->toDateString();
            });
 
        foreach ($attendancesByMonth as $month => $attendances) {
            // すでに Payroll があるか確認
            $existingPayroll = Payroll::where('user_id', $user->id)
                ->where('month', $month)
                ->first();
 
            if ($existingPayroll) {
                continue; // 上書きしない
            }
 
            $totalHours = 0;
            $totalPay = 0;
            $hourlyWage = $attendances->last()->hourly_wage ?? 0;
 
            foreach ($attendances as $att) {
                $clockIn = Carbon::parse($att->date . ' ' . $att->clock_in);
                $clockOut = Carbon::parse($att->date . ' ' . $att->clock_out);
 
                // 日跨ぎ対応
                if ($clockOut->lessThanOrEqualTo($clockIn)) {
                    $clockOut->addDay();
                }
 
                $totalMinutes = $clockIn->diffInMinutes($clockOut);
 
                // 深夜時間帯を1分単位で計算（22:00〜翌5:00）
                $nightMinutes = 0;
                $current = $clockIn->copy();
                while ($current->lt($clockOut)) {
                    $hour = (int)$current->format('H');
                    if ($hour >= 22 || $hour < 5) {
                        $nightMinutes++;
                    }
                    $current->addMinute();
                }
 
                $normalMinutes = max(0, $totalMinutes - $nightMinutes);
 
                $normalPay = ($normalMinutes / 60) * $hourlyWage;
                $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
 
                $totalHours += $totalMinutes / 60;
                $totalPay += $normalPay + $nightPay;
            }
 
            Payroll::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'month' => $month,
                'total_hours' => round($totalHours, 2),
                'hourly_wage' => $hourlyWage,
                'total_pay' => round($totalPay),
            ]);
        }
    }
 
    return redirect()->back()->with('success', '勤怠から給与を生成しました。既存給与は上書きされません。');
}
 
 
 
    // 💵 給与一覧
    public function payrolls(Request $request, $companyId)
{
    $company = Company::findOrFail($companyId);
   
    $query = Attendance::with('user')
        ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
        ->whereNotNull('clock_in')
        ->whereNotNull('clock_out');
 
    if ($request->month) {
        $query->where('date', 'like', $request->month . '%');
    }
 
    if ($request->user_id) {
        $query->where('user_id', $request->user_id);
    }
 
    $attendances = $query->orderBy('date', 'desc')->get();
 
    // 勤務時間と給与を計算（深夜手当・日跨ぎ対応）
    foreach ($attendances as $attendance) {
        if ($attendance->clock_in && $attendance->clock_out) {
            $clockIn = Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
            $clockOut = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
 
            // 日跨ぎ対応
            if ($clockOut->lessThanOrEqualTo($clockIn)) {
                $clockOut->addDay();
            }
 
            $totalMinutes = $clockIn->diffInMinutes($clockOut);
 
            // 深夜時間を1分単位で計算（22:00〜翌5:00）
            $nightMinutes = 0;
            $current = $clockIn->copy();
            while ($current->lt($clockOut)) {
                $hour = (int)$current->format('H');
                if ($hour >= 22 || $hour < 5) {
                    $nightMinutes++;
                }
                $current->addMinute();
            }
 
            $normalMinutes = max(0, $totalMinutes - $nightMinutes);
 
            $hourlyWage = $attendance->hourly_wage ?? 0;
            $normalPay = ($normalMinutes / 60) * $hourlyWage;
            $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
 
            $attendance->hours = round($totalMinutes / 60, 2);
            $attendance->pay = round($normalPay + $nightPay);
            $attendance->effective_wage = $hourlyWage;
        } else {
            $attendance->hours = 0;
            $attendance->pay = 0;
            $attendance->effective_wage = 0;
        }
    }
 
    $users = User::where('company_id', $companyId)->get();
 
    return view('company.payrolls', compact('attendances', 'users', 'company'));
}
 
 
 
    // 👤 社員登録フォーム
    public function createEmployee($companyId)
    {
        $company = Company::findOrFail($companyId);
        $stores = Store::where('company_id', $companyId)->get();
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
 
    // 🔔 最近の出退勤ログ
    public function recentLogs(Company $company, Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $recent_attendances = Attendance::whereHas('user', fn($q) => $q->where('company_id', $company->id))
            ->whereDate('date', $date)
            ->with('user')
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();
 
        return view('company.partials.recent_logs', compact('recent_attendances'))->render();
    }
 
    // 👤 社員編集
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
 
 
    // 👤 社員削除
    public function deleteEmployee($companyId, $employeeId)
    {
        $company = Company::findOrFail($companyId);
        $employee = User::findOrFail($employeeId);
 
        if ($employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }
 
        $employee->delete();
 
        return redirect()->route('company.employees', $company->id)
            ->with('success', '社員を削除しました。');
    }
 
  // CSV出力
public function payrollsCsv(Company $company)
{
    $month = request('month', now()->format('Y-m'));
    $attendances = $company->attendances()
        ->whereYear('date', Carbon::parse($month)->year)
        ->whereMonth('date', Carbon::parse($month)->month)
        ->get();
 
    $csvData = "社員名,日付,出勤,退勤,勤務時間(h),時給,給与(円)\n";
 
    foreach ($attendances as $attendance) {
        if (!$attendance->clock_in || !$attendance->clock_out) continue;
 
        // 出勤・退勤日時
        $clockIn = Carbon::parse($attendance->date . ' ' . $attendance->clock_in);
        $clockOut = Carbon::parse($attendance->date . ' ' . $attendance->clock_out);
 
        // ⏰ 日跨ぎ対応（退勤が翌日）
        if ($clockOut->lessThanOrEqualTo($clockIn)) {
            $clockOut->addDay();
        }
 
        // 深夜時間帯の設定（22:00〜翌5:00）
        $nightStart = Carbon::parse($attendance->date . ' 22:00');
        $nightEnd = Carbon::parse($attendance->date . ' 05:00')->addDay();
 
        // 深夜労働時間を計算
        $overlapStart = $clockIn->greaterThan($nightStart) ? $clockIn : $nightStart;
        $overlapEnd = $clockOut->lessThan($nightEnd) ? $clockOut : $nightEnd;
 
        $nightMinutes = $overlapEnd->gt($overlapStart)
            ? $overlapStart->diffInMinutes($overlapEnd)
            : 0;
 
        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        $normalMinutes = max(0, $totalMinutes - $nightMinutes);
 
        // 時給を取得（WageHistory or User）
        $wageHistory = WageHistory::where('user_id', $attendance->user_id)
            ->where('effective_from', '<=', $attendance->date)
            ->orderByDesc('effective_from')
            ->first();
 
        $hourlyWage = $wageHistory
            ? $wageHistory->hourly_wage
            : ($attendance->user->hourly_wage ?? 0);
 
        // 給与計算（深夜25%割増）
        $normalPay = ($normalMinutes / 60) * $hourlyWage;
        $nightPay = ($nightMinutes / 60) * $hourlyWage * 1.25;
        $pay = round($normalPay + $nightPay);
 
        // 合計勤務時間（時間単位）
        $hours = round($totalMinutes / 60, 2);
 
        // CSV 1行追加
        $csvData .= implode(',', [
            $attendance->user->name,
            Carbon::parse($attendance->date)->format('Y/m/d'),
            Carbon::parse($attendance->clock_in)->format('H:i'),
            Carbon::parse($attendance->clock_out)->format('H:i'),
            number_format($hours, 2),
            $hourlyWage,
            $pay
        ]) . "\n";
    }
 
    $csvData = mb_convert_encoding($csvData, 'SJIS-win', 'UTF-8');
    $filename = $company->name . '-' . str_replace('-', '', $month) . '.csv';
 
    return response($csvData)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', "attachment; filename={$filename}");
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
        'date' => 'required|date|before_or_equal:today',
        'clock_in' => ['nullable', 'regex:/^([0-2]?[0-9]):[0-5][0-9]$/'],
        'clock_out' => ['nullable', 'regex:/^([0-2]?[0-9]):[0-5][0-9]$/'],
    ]);
 
    $date = $validated['date'];
    $clockIn = $validated['clock_in'];
    $clockOut = $validated['clock_out'];
 
    // 🔸 同じ日・同じユーザーの勤怠が既に存在するかチェック
    $exists = \App\Models\Attendance::where('user_id', $validated['user_id'])
        ->where('date', $date)
        ->exists();
 
    if ($exists) {
        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'この社員はすでに出勤しています。');
    }
 
    // 🔸 時刻文字列をCarbonに変換（日跨ぎ対応）
    $parseTime = function ($baseDate, $time) {
        if (!$time) return null;
 
        [$hour, $minute] = explode(':', $time);
 
        // 24時以上なら翌日扱いに補正
        if ((int)$hour >= 24) {
            $hour -= 24;
            return \Carbon\Carbon::parse($baseDate)->addDay()->setTime($hour, (int)$minute);
        }
 
        return \Carbon\Carbon::parse($baseDate)->setTime((int)$hour, (int)$minute);
    };
 
    $clockInCarbon = $parseTime($date, $clockIn);
    $clockOutCarbon = $parseTime($date, $clockOut);
 
    // 🔸 退勤が出勤より前なら翌日扱い
    if ($clockInCarbon && $clockOutCarbon && $clockOutCarbon->lessThanOrEqualTo($clockInCarbon)) {
        $clockOutCarbon->addDay();
    }
 
    // 🔸 時給を取得（履歴優先）
    $wageHistory = \App\Models\WageHistory::where('user_id', $validated['user_id'])
        ->where('effective_from', '<=', $date)
        ->orderByDesc('effective_from')
        ->first();
 
    $hourlyWage = $wageHistory
        ? $wageHistory->hourly_wage
        : \App\Models\User::find($validated['user_id'])->hourly_wage;
 
    // 🔸 勤怠データを登録
    \App\Models\Attendance::create([
        'user_id' => $validated['user_id'],
        'company_id' => $company->id,
        'date' => $date,
        'clock_in' => $clockInCarbon,
        'clock_out' => $clockOutCarbon,
        'hourly_wage' => $hourlyWage,
    ]);
 
    return redirect()
        ->route('company.attendances', ['company' => $company->id])
        ->with('success', '勤怠を追加しました。');
}
 
 
 
    // 給与再計算（今月）
    public function recalculatePayroll(Company $company)
    {
        $users = $company->users;
 
        foreach ($users as $user) {
            $totalHours = Attendance::where('user_id', $user->id)
                ->whereMonth('date', now()->month)
                ->sum(DB::raw('TIMESTAMPDIFF(HOUR, clock_in, clock_out)'));
 
            Payroll::updateOrCreate(
                ['user_id' => $user->id, 'month' => now()->startOfMonth()],
                [
                    'company_id' => $company->id,
                    'hourly_wage' => $user->hourly_wage,
                    'total_hours' => $totalHours,
                    'total_pay' => $user->hourly_wage * $totalHours,
                ]
            );
        }
 
        return redirect()->route('company.payrolls', $company->id)
            ->with('success', '給与データを再計算しました。');
    }
 
    // 時給更新
    public function updateWage(Request $request, Company $company, User $employee)
{
    // 1️⃣ バリデーション
    $validated = $request->validate([
        'hourly_wage' => 'required|numeric|min:0',
    ]);
 
    // 2️⃣ WageHistory に追加（履歴管理）
    $latestWage = WageHistory::where('user_id', $employee->id)
        ->orderByDesc('effective_from')
        ->first();
 
    if (!$latestWage || $latestWage->hourly_wage != $validated['hourly_wage']) {
        WageHistory::create([
            'user_id' => $employee->id,
            'hourly_wage' => $validated['hourly_wage'],
            'effective_from' => now()->toDateString(),
        ]);
    }
 
    // 3️⃣ users テーブルの hourly_wage を更新（今後の勤怠に反映）
    $employee->hourly_wage = $validated['hourly_wage'];
    $employee->save();
 
    // 4️⃣ 最新の Payroll にも反映させる（既存の給与データを修正したい場合）
    $latestPayroll = \App\Models\Payroll::where('user_id', $employee->id)
        ->orderByDesc('month')
        ->first();
 
    if ($latestPayroll) {
        $totalPay = $latestPayroll->total_hours * $validated['hourly_wage'];
        $latestPayroll->update([
            'hourly_wage' => $validated['hourly_wage'],
            'total_pay' => $totalPay,
        ]);
    }
 
    // 5️⃣ 完了メッセージを返す
    return redirect()->back()->with('success', "{$employee->name} さんの時給を更新しました（以降の勤務・最新給与に反映されます）。");
}
 
public function destroyAttendance(Company $company, Attendance $attendance)
{
    // 他社の勤怠を削除させない（安全対策）
    if ($attendance->user->company_id !== $company->id) {
        abort(403, '他社の勤怠は削除できません');
    }
 
    // ✅ 過去でも削除可能に変更
    $attendance->delete();
 
    return redirect()
        ->route('company.attendances', $company->id)
        ->with('success', '勤怠データを削除しました。');
}

public function update(Request $request, Company $company)
{
    $request->validate([
        'closing_day' => 'required|integer|min:1|max:31',
    ]);

    $company->update([
        'closing_day' => $request->closing_day,
    ]);

    return back()->with('success', '締め日を更新しました');
}


 
 
}
 
