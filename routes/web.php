<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/shift/login/{line_user_id}', [ShiftController::class, 'loginWithLine'])->name('shift.login');


// 🔸 LINE Webhook（CSRF除外）
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// 🔸 ログイン / ログアウト
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 🔸 ホーム → ログインへリダイレクト
Route::get('/', fn() => redirect('/login'));

// 🔹 LINE経由のシフト登録用（ログイン不要）
Route::get('/shift/login/{line_user_id}', [ShiftController::class, 'loginWithLine'])
    ->name('shift.login');

// ✅ 認証が必要なルート
Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 企業管理（会社ごと）
    |--------------------------------------------------------------------------
    */
    Route::prefix('company/{company}')->name('company.')->group(function() {

        // ダッシュボード
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');

        // 勤怠
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('attendances');
        Route::put('/attendances/{attendance}', [CompanyController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [CompanyController::class, 'destroyAttendance'])->name('attendances.destroy');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');

        // 勤怠追加
        Route::get('/attendances/create', [CompanyController::class, 'createAttendance'])->name('attendances.create');
        Route::post('/attendances', [CompanyController::class, 'storeAttendance'])->name('attendances.store');

        // 社員管理
        Route::get('/employees', [CompanyController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [CompanyController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [CompanyController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [CompanyController::class, 'editEmployee'])->name('employees.edit');
        Route::post('/employees/{employee}', [CompanyController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [CompanyController::class, 'deleteEmployee'])->name('employees.delete');
        Route::put('/employees/{employee}/update-wage', [CompanyController::class, 'updateWage'])
            ->name('employees.updateWage');



        // 給与
        Route::get('/payrolls', [CompanyController::class, 'payrolls'])->name('payrolls');
        Route::get('/generate-payroll', [CompanyController::class, 'generatePayroll'])->name('generatePayroll');
        Route::get('/payrolls/pdf', [CompanyController::class, 'payrollsPdf'])->name('payrollsPdf');
        Route::get('/payrolls/csv', [CompanyController::class, 'payrollsCsv'])->name('payrollsCsv');
        Route::get('/payrolls/recalculate', [CompanyController::class, 'recalculatePayroll'])
            ->name('payrolls.recalculate');


    });

    /*
    |--------------------------------------------------------------------------
    | 共通給与計算
    |--------------------------------------------------------------------------
    */
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/calculate', [PayrollController::class, 'calculatePayroll'])->name('payroll.calculate');

    /*
    |--------------------------------------------------------------------------
    | シフト登録・表示（ログイン後）
    |--------------------------------------------------------------------------
    */
 Route::get('/shift/calendar/{user}', [ShiftController::class, 'calendar'])->name('shift.calendar');
Route::get('/shift/events/{user}', [ShiftController::class, 'events'])->name('shift.events');
// ✅ シフト登録保存（LINEログイン用）
Route::post('/shift/save', [ShiftController::class, 'save'])->name('shift.save');
// まとめて保存
// シフトまとめ保存
Route::post('/shift/save-all', [ShiftController::class, 'saveAll'])->name('shift.saveAll');




   
});

