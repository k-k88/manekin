<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Store;
use Carbon\Carbon;

class CompanyController extends Controller
{
    public function dashboard(Company $company)
    {
        $user = Auth::user();

        // 所属企業チェック
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // 本日の出勤数
        $today_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereDate('date', $today) // date カラムを使用
          ->count();

        // 登録社員数
        $employee_count = User::where('company_id', $company->id)->count();

        // 登録店舗数
        $store_count = Store::where('company_id', $company->id)->count();

        // 今月の出勤数
        $monthly_attendance_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereBetween('date', [$startOfMonth, $endOfMonth])
          ->count();

        // 出勤中社員数（退勤していない社員）
        $active_employee_count = Attendance::whereHas('user', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })->whereNull('clock_out') // 退勤していない
          ->count();

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
        // 会社を取得
        $company = Company::findOrFail($companyId);

        // その会社に属する従業員を取得
        $employees = User::where('company_id', $companyId)->get();

        // ビューに渡す
        return view('company.employees', compact('company', 'employees'));
    }
public function attendances($companyId)
{
    // 会社情報を取得
    $company = \App\Models\Company::findOrFail($companyId);

    // 勤怠情報を取得（例: attendancesテーブルが存在する前提）
    $attendances = \App\Models\Attendance::whereHas('user', function ($query) use ($companyId) {
        $query->where('company_id', $companyId);
    })->with('user')->get();

    // ビューへ渡す
    return view('company.attendances', compact('company', 'attendances'));
}

}
