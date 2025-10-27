<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;

// 🔹 LINE Webhook（CSRF除外）
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// 🔹 ログイン機能
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 🔹 ホーム画面
Route::get('/', function () {
    return redirect('/login');
});

// 🔹 認証必須ルート
Route::middleware(['auth'])->group(function () {

    // ✅ 企業関連ルート
    Route::prefix('company/{company}')->group(function () {
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('company.dashboard');
        Route::get('/employees', [CompanyController::class, 'employees'])->name('company.employees');
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('company.attendances');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('company.recentLogs');

        // 🔹 社員管理
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('company.employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('company.employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('company.employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('company.employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('company.employees.delete');

        // 🔹 勤怠削除
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('company.attendances.destroy');

        // 🔹 給与関連
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('company.payrolls');
        Route::get('/generate-payroll', [CompanyController::class, 'generatePayroll'])->name('company.generatePayroll');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('company.payrollsPdf');
    });

    // 🔹 社員追加（旧仕様互換）
    Route::get('/company/{company}/users/create', [CompanyController::class, 'createUser'])->name('company.users.create');
    Route::post('/company/{company}/users', [CompanyController::class, 'storeUser'])->name('company.users.store');
});

// 🔹 給与計算ページ
Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
Route::post('/payroll/calculate', [PayrollController::class, 'calculatePayroll'])->name('payroll.calculate');
