<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

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
    | 企業ごとの管理ルート
    |--------------------------------------------------------------------------
    */
    Route::prefix('company/{company}')->name('company.')->group(function () {

        // 🏢 ダッシュボード
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');
        Route::get('/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/update', [CompanyController::class, 'update'])->name('update');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');

        /*
        |--------------------------------------------------------------------------
        | 👥 社員管理（EmployeeController）
        |--------------------------------------------------------------------------
        */
        Route::get('/employees', [EmployeeController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [EmployeeController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'editEmployee'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'deleteEmployee'])->name('employees.delete');
        Route::put('/employees/{employee}/wage', [EmployeeController::class, 'updateWage'])->name('employees.wage.update');
        Route::put('/employees/{employee}/update-wage', [EmployeeController::class, 'updateWage'])->name('employees.updateWage');

        /*
        |--------------------------------------------------------------------------
        | 🕒 勤怠管理（AttendanceController）
        |--------------------------------------------------------------------------
        */
        Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances');
        Route::get('/attendances/create', [AttendanceController::class, 'createAttendance'])->name('attendances.create');
        Route::post('/attendances', [AttendanceController::class, 'storeAttendance'])->name('attendances.store');
        Route::get('/attendances/{attendance}/edit', [AttendanceController::class, 'editAttendance'])->name('attendances.edit');
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroyAttendance'])->name('attendances.destroy');

        /*
        |--------------------------------------------------------------------------
        | 💰 給与管理（PayrollController）
        |--------------------------------------------------------------------------
        */
        Route::get('/payrolls', [PayrollController::class, 'payrolls'])->name('payrolls');
        Route::get('/payrolls/csv', [PayrollController::class, 'payrollsCsv'])->name('payrollsCsv');
        Route::get('/payrolls/create', [PayrollController::class, 'createPayroll'])->name('payrolls.create');
        Route::post('/payrolls', [PayrollController::class, 'storePayroll'])->name('payrolls.store');
        Route::get('/payrolls/{payroll}/edit', [PayrollController::class, 'editPayroll'])->name('payrolls.edit');
        Route::put('/payrolls/{payroll}', [PayrollController::class, 'updatePayroll'])->name('payrolls.update');
        Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroyPayroll'])->name('payrolls.destroy');
        Route::post('/payrolls/recalculate', [PayrollController::class, 'recalculate'])->name('payrolls.recalculate');
    });

    /*
    |--------------------------------------------------------------------------
    | 共通給与計算（全体向け）
    |--------------------------------------------------------------------------
    */
    Route::get('/payroll', [PayrollController::class, 'indexGlobal'])->name('payroll.index');
    Route::post('/payroll/calculate', [PayrollController::class, 'calculatePayroll'])->name('payroll.calculate');

    /*
    |--------------------------------------------------------------------------
    | シフト登録・表示（ログイン後）
    |--------------------------------------------------------------------------
    */
    Route::get('/shift/calendar/{user}', [ShiftController::class, 'calendar'])->name('shift.calendar');
    Route::get('/shift/events/{user}', [ShiftController::class, 'events'])->name('shift.events');
    Route::post('/shift/save', [ShiftController::class, 'save'])->name('shift.save');
    Route::post('/shift/save-all', [ShiftController::class, 'saveAll'])->name('shift.saveAll');
    Route::get('/company/{company}/shifts/edit', [ShiftController::class, 'edit'])->name('company.shifts.edit');
    Route::get('/company/{company}/shifts/delete', [ShiftController::class, 'delete'])->name('company.shifts.delete');
}); 
