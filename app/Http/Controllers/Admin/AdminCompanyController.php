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

}