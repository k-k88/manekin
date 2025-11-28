<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminCompanyController extends Controller
{
    public function index()
{
    $companies = Company::withCount([
        'users as admin_count' => function ($q) {
            $q->where('role', 'company_admin');
        },
        'users as user_count'
    ])->get();

    return view('admin.companies.index', compact('companies'));
}

    public function create()
    {
        return view('admin.companies.create');
    }

 public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:10|unique:companies,code',
    ]);

    $company = Company::create([
        'name' => $request->name,
        'code' => strtoupper($request->code),
    ]);

    // ✅ 作成直後に管理者作成画面へ飛ばす
    return redirect()->route('admin.companies.admin.create', $company->id)
        ->with('success', '会社を作成しました。次に管理者ユーザーを作成してください。');
}
public function edit(Company $company)
{
    return view('admin.companies.edit', compact('company'));
}

public function update(Request $request, User $user)
{
    $validated = $request->validate([
        'name'  => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
        'role'  => 'required|in:company_admin,employee',
    ]);

    $user->update($validated);

    return redirect()->route('admin.users.index')
        ->with('success', 'ユーザー情報を更新しました。');
}


public function destroy(Company $company)
{
    $company->delete();

    return redirect()->route('admin.companies.index')->with('success', '会社を削除しました。');
}


}