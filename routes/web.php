<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftApprovalController;

// ================================
// LINE Webhook（CSRF除外）
// ================================
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// ================================
// ログイン / ログアウト
// ================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ホーム → ログイン
Route::get('/', fn() => redirect('/login'));

// LINEシフトログイン
Route::get('/shift/login/{line_user_id}', [ShiftController::class, 'loginWithLine'])->name('shift.login');

// ================================
// 認証必須ルート
// ================================
Route::middleware(['auth'])->group(function () {

    // -----------------------------
    // 会社ごとのルート（管理者用）
    // -----------------------------
    Route::prefix('company/{company}')->name('company.')->group(function () {

        // ダッシュボード
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');

        // 出退勤管理
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('attendances');
        Route::put('/attendances/{attendance}', [CompanyController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('attendances.destroy');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');
        Route::get('/attendances/create', [CompanyController::class, 'createAttendance'])->name('attendances.create');
        Route::post('/attendances', [CompanyController::class, 'storeAttendance'])->name('attendances.store');

        // 社員管理
        Route::get('/employees', [CompanyController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('employees.delete');
        Route::put('/employees/{employee}/update-wage', [CompanyController::class, 'updateWage'])->name('employees.updateWage');

        // 給与管理
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('payrolls');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('payrollsPdf');
        Route::get('/payrolls/csv', [CompanyController::class, 'payrollsCsv'])->name('payrollsCsv');
        Route::get('/payrolls/recalculate', [CompanyController::class, 'recalculatePayroll'])->name('payrolls.recalculate');

        // -----------------------------
        // シフト承認フロー（管理者）
        // -----------------------------
        Route::get('/shift/requests', [ShiftApprovalController::class, 'index'])->name('shift.requests');
        Route::post('/shift/requests/{requestModel}/approve', [ShiftApprovalController::class, 'approve'])->name('shift.requests.approve');
        Route::post('/shift/requests/{requestModel}/reject', [ShiftApprovalController::class, 'reject'])->name('shift.requests.reject');
        Route::post('/shift/requests/approve-all', [ShiftApprovalController::class, 'approveAll'])->name('shift.requests.approveAll');

        // シフト編集カレンダー（管理者）
        Route::get('/shift/edit', [ShiftApprovalController::class, 'calendar'])->name('shift.calendar.edit');
        Route::post('/shift/edit', [ShiftApprovalController::class, 'calendarSave'])->name('shift.calendar.save');

        // シフト確定保存（承認済み）
        Route::post('/shift/save', [ShiftApprovalController::class, 'save'])->name('shift.save');

        // シフト削除
        Route::get('/shift/delete', [ShiftApprovalController::class, 'deletePage'])->name('shift.delete.page');
        Route::delete('/shift/delete', [ShiftApprovalController::class, 'delete'])->name('shift.delete');

        // 管理者用：日付ごとの希望シフト取得（Ajax）
        Route::get('/shift/requests/{date}', [ShiftApprovalController::class, 'getRequestsByDate'])
            ->name('shift.requests.by_date');
    });

    // -----------------------------
    // 従業員自身によるシフト提出（スマホ用）
    // -----------------------------
    Route::get('/shift/calendar/{user}', [ShiftController::class, 'calendar'])->name('shift.user.calendar');
    Route::get('/shift/events/{user}', [ShiftController::class, 'events'])->name('shift.user.events');
    Route::post('/shift/save', [ShiftController::class, 'save'])->name('shift.user.save');
    Route::post('/shift/save-all', [ShiftController::class, 'saveAll'])->name('shift.user.saveAll');
    Route::delete('/shift/{shift}', [ShiftController::class, 'delete'])->name('shift.user.delete');
});

// -----------------------------
// 会社情報更新
// -----------------------------
Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');

Route::middleware(['auth'])->group(function () {
    Route::post('/company/{company}/attendance/create', [CompanyController::class, 'createAttendance'])
        ->name('company.createAttendance');
});

Route::post('/company/{company}/attendance', [CompanyController::class, 'store'])
    ->name('company.attendances.store');


Route::get('/company/{company}/shift/requests/{date}', [ShiftApprovalController::class, 'getRequestsByDate'])
    ->name('company.shift.requests');

Route::post('/company/{company}/shift/save', [ShiftApprovalController::class, 'save'])
    ->name('company.shift.save');

Route::post('/companies/{company}/shifts/save', [ShiftApprovalController::class, 'save']);
Route::delete('/companies/{company}/shifts/{shift}', [ShiftApprovalController::class, 'destroy']);

Route::get('/company/{company}/shift/{id}', [ShiftApprovalController::class, 'show'])->name('shift.show');
Route::delete('/company/{company}/shift/{id}', [ShiftApprovalController::class, 'destroy'])->name('shift.destroy');
