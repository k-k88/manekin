<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;

class AdminUserController extends Controller
{
    public function index()
{
    $companies = Company::orderBy('name')->get();
    return view('admin.users.index', compact('companies'));
}

    public function createCompanyAdmin(Company $company)
    {
        return view('admin.users.create-company-admin', compact('company'));
    }

    public function storeCompanyAdmin(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'company_id' => $company->id,
            'role'       => 'company_admin',
            'password'   => bcrypt($request->password),
        ]);

        return redirect()->route('admin.companies.index')
            ->with('success', '管理者ユーザーを作成しました！');
    
    }
    public function byCompany(Request $request)
{
    $request->validate([
        'company_id' => 'required|exists:companies,id'
    ]);

    $company = Company::findOrFail($request->company_id);
    $users = User::where('company_id', $company->id)->orderBy('name')->get();

    return view('admin.users.list', compact('company', 'users'));
}

}
