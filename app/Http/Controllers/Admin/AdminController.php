<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller; // ← これが必要！！
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'companyCount' => Company::count(),
            'adminCount'   => User::where('role', 'company_admin')->count(),
            'userCount'    => User::count(),
        ]);
    }
}
