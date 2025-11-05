<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftApprovalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ✅ LINE Webhook（CSRF除外）
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// ✅ ログイン / ログアウト
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ✅ ホーム → ログイン
Route::get('/', fn() => redirect('/login'));

// ✅ LINEから自動シフトログイン
Route::get('/shift/login/{line_user_id}', [ShiftController::class, 'loginWithLine'])->name('shift.login');

// ✅ ログイン必須
Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 企業管理
    |--------------------------------------------------------------------------
    */
    Route::prefix('company/{company}')->name('company.')->group(function() {

        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');

        // 出退勤
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('attendances');
        Route::put('/attendances/{attendance}', [CompanyController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('attendances.destroy');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');
            // ✅ 勤怠追加フォーム
        Route::get('/attendances/create', [CompanyController::class, 'createAttendance'])->name('attendances.create');

    // ✅ 勤怠登録処理
    Route::post('/attendances', [CompanyController::class, 'storeAttendance'])->name('attendances.store');

        // 社員
        Route::get('/employees', [CompanyController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('employees.delete');
        Route::put('/employees/{employee}/update-wage', [CompanyController::class, 'updateWage'])->name('employees.updateWage');

        // 給与
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('payrolls');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('payrollsPdf');
        Route::get('/payrolls/csv', [CompanyController::class, 'payrollsCsv'])->name('payrollsCsv');
        Route::get('/payrolls/recalculate', [CompanyController::class, 'recalculatePayroll'])->name('payrolls.recalculate');

        /*
        |--------------------------------------------------------------------------
        | ✅ シフト提出 → 店長承認フロー（企業単位）
        |--------------------------------------------------------------------------
        */
        Route::get('/shift/requests', [ShiftApprovalController::class, 'index'])->name('shift.requests');
        Route::post('/shift/requests/{requestModel}/approve', [ShiftApprovalController::class, 'approve'])->name('shift.requests.approve');
        Route::post('/shift/requests/{requestModel}/reject', [ShiftApprovalController::class, 'reject'])->name('shift.requests.reject');
        Route::post('/shift/requests/approve-all', [ShiftApprovalController::class, 'approveAll'])->name('shift.requests.approveAll');

    });

    /*
    |--------------------------------------------------------------------------
    | 従業員シフト提出 UI
    |--------------------------------------------------------------------------
    */
    Route::get('/shift/calendar/{user}', [ShiftController::class, 'calendar'])->name('shift.calendar');
    Route::post('/shift/save', [ShiftController::class, 'save'])->name('shift.save');
    Route::post('/shift/save-all', [ShiftController::class, 'saveAll'])->name('shift.saveAll');

    /*
    |--------------------------------------------------------------------------
    | ✅ 店長用 シフト編集/削除（確定側）
    |--------------------------------------------------------------------------
    */
    Route::get('/company/{company}/shifts/edit', [ShiftController::class, 'edit'])->name('company.shifts.edit');
    Route::get('/company/{company}/shifts/delete', [ShiftController::class, 'delete'])->name('company.shifts.delete');
});
