<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    /**
     * 社員一覧
     */
    public function employees(Company $company)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $employees = User::where('company_id', $company->id)
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('company.employees', compact('company', 'employees'));
    }

    /**
     * 社員作成画面
     */
    public function createEmployee(Company $company)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $stores = \App\Models\Store::where('company_id', $company->id)->get();

        return view('company.employees_create', compact('company', 'stores'));
    }

    /**
     * 社員登録処理
     */
public function storeEmployee(Request $request, Company $company)
{
    $user = auth()->user();
    if ($user->company_id !== $company->id) {
        abort(403, 'アクセス権がありません');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'phone' => 'required|string|max:20', // ✅ 電話番号を追加
        'password' => 'required|string|min:6|confirmed',
        'store_id' => 'required|exists:stores,id',
        'hire_date' => 'required|date',
        'role' => 'required|string',
        'hourly_wage' => 'nullable|numeric|min:0', // ✅ 必須解除
    ]);

    User::create([
        'company_id' => $company->id,
        'store_id' => $validated['store_id'],
        'name' => $validated['name'],
        'email' => $validated['email'],
        'phone' => $validated['phone'], // ✅ ここで保存
        'password' => Hash::make($validated['password']),
        'hourly_wage' => $validated['hourly_wage'] ?? null,
        'hire_date' => $validated['hire_date'],
        'role' => $validated['role'],
    ]);

    return redirect()->route('company.employees', $company)->with('success', '社員を追加しました。');
}


    /**
     * 社員編集画面
     */
    public function editEmployee(Company $company, User $employee)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id || $employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $stores = \App\Models\Store::where('company_id', $company->id)->get();

        return view('company.employees_edit', compact('company', 'employee', 'stores'));
    }

    /**
     * 社員情報更新
     */
    public function updateEmployee(Request $request, Company $company, User $employee)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id || $employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $employee->id,
            'hourly_wage' => 'nullable|numeric|min:0', // ✅ nullable に変更
            'store_id' => 'required|exists:stores,id',
            'hire_date' => 'required|date',
        ]);

        // ✅ 空なら会社デフォルト時給または現行値を維持
        $validated['hourly_wage'] = $validated['hourly_wage'] ?? $company->default_hourly_wage ?? $employee->hourly_wage;

        $employee->update($validated);

        return redirect()->route('company.employees', $company)->with('success', '社員情報を更新しました。');
    }

    /**
     * 社員削除
     */
    public function deleteEmployee(Company $company, User $employee)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id || $employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $employee->delete();
        return back()->with('success', '社員を削除しました。');
    }

    /**
     * 時給更新
     */
    public function updateWage(Request $request, Company $company, User $employee)
    {
        $user = auth()->user();
        if ($user->company_id !== $company->id || $employee->company_id !== $company->id) {
            abort(403, 'アクセス権がありません');
        }

        $validated = $request->validate([
            'hourly_wage' => 'required|numeric|min:0',
        ]);

        $employee->update([
            'hourly_wage' => $validated['hourly_wage'],
        ]);

        \App\Models\WageHistory::create([
            'user_id' => $employee->id,
            'hourly_wage' => $validated['hourly_wage'],
            'effective_from' => now(),
        ]);

        return back()->with('success', '時給を更新しました。');
    }
}
