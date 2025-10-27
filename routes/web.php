<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;

// LINE Webhook（CSRF除外）
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// ログイン
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ホーム
Route::get('/', fn() => redirect('/login'));

// 認証必須
Route::middleware(['auth'])->group(function () {

    // 企業関連
    Route::prefix('company/{company}')->group(function () {

        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('company.dashboard');

        // 社員管理
        Route::get('/employees', [CompanyController::class, 'employees'])->name('company.employees');
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('company.employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('company.employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('company.employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('company.employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('company.employees.delete');

        // 勤怠
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('company.attendances');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('company.recentLogs');
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('company.attendances.destroy');

        // 給与
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('company.payrolls');
        Route::get('/generate-payroll', [CompanyController::class, 'generatePayroll'])->name('company.generatePayroll');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('company.payrollsPdf');

        // ユーザー（互換用）
        Route::get('/users/create', [CompanyController::class, 'createUser'])->name('company.users.create');
        Route::post('/users', [CompanyController::class, 'storeUser'])->name('company.users.store');
    });

    // 給与計算ページ（共通）
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/calculate', [PayrollController::class, 'calculatePayroll'])->name('payroll.calculate');

    Route::get('/company/{company}/payrolls/csv', [CompanyController::class, 'payrollsCsv'])
    ->name('company.payrollsCsv');



});
