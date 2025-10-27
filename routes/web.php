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
    Route::prefix('company/{company}')->name('company.')->middleware('auth')->group(function() {

        // ダッシュボード
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');

        // 勤怠
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('attendances');
        Route::put('/attendances/{attendance}', [CompanyController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('attendances.destroy');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');

        // 社員管理
        Route::get('/employees', [CompanyController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('employees.delete');

        // 給与
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('payrolls');
        Route::get('/generate-payroll', [CompanyController::class, 'generatePayroll'])->name('generatePayroll');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('payrollsPdf');
        Route::get('/payrolls/csv', [CompanyController::class, 'payrollsCsv'])->name('payrollsCsv');

    });

    // 共通給与計算
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/calculate', [PayrollController::class, 'calculatePayroll'])->name('payroll.calculate');
});
