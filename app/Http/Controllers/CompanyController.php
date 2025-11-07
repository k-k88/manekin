<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Attendance;
use App\Models\ShiftRequest;
use App\Models\Payroll;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CompanyController extends Controller
{
    /**
     * ダッシュボード表示
     */
    public function dashboard(Company $company)
    {
        $user = Auth::user();

        // アクセス制限：他社のダッシュボードには入れない
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        // 👥 登録社員数
        $employee_count = $company->employees()->count();

        // 🏬 登録店舗数
        $store_count = $company->stores()->count();

        // 🕓 全勤怠データ数
        $attendance_count = $company->attendances()->count();

        // 📅 本日の出勤数
        $today_attendance_count = $company->attendances()
            ->whereDate('date', Carbon::today())
            ->count();

        // 🕒 最近の出退勤履歴（最新5件）
        $recent_attendances = $company->attendances()
            ->with('user')
            ->whereHas('user')
            ->orderByDesc('date')
            ->orderByDesc('clock_in')
            ->take(5)
            ->get();

        return view('company.dashboard', [
            'company' => $company,
            'employee_count' => $employee_count,
            'store_count' => $store_count,
            'attendance_count' => $attendance_count,
            'today_attendance_count' => $today_attendance_count,
            'recent_attendances' => $recent_attendances,
        ]);
    }

    /**
     * 🔁 AJAXで最新の勤怠ログを取得
     */
    public function recentLogs(Company $company, Request $request)
    {
        $user = Auth::user();

        // アクセス制限
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        // 📅 表示したい日付（デフォルトは今日）
        $date = $request->query('date', now()->toDateString());

        // 🕓 指定日の勤怠ログのみ取得（過去追加分は除外）
        $recent_attendances = Attendance::where('company_id', $company->id)
            ->whereDate('date', '=', $date)
            ->with('user')
            ->orderBy('clock_in', 'asc')
            ->take(20)
            ->get();

        return view('company.partials.recent_logs', compact('recent_attendances', 'date'));
    }

    /**
     * 会社設定編集画面
     */
    public function edit(Company $company)
    {
        $user = Auth::user();

        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        return view('company.edit', compact('company'));
    }

    /**
     * 会社情報更新処理（締め日も含む）
     */
    public function update(Request $request, Company $company)
    {
        $user = Auth::user();

        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255', // ダッシュボードからはnameが来ない場合もあるため「required」→「sometimes」に
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'closing_day' => 'nullable|integer|min:1|max:31', // ← 🔧 締め日追加
        ]);

        $company->update($validated);

        return redirect()
            ->route('company.dashboard', $company)
            ->with('success', '会社情報を更新しました。');
    }
}
