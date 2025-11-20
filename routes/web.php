<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftApprovalController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StoreController;

// ★ システム管理者用
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminUserController;


// ================================
// LINE Webhook（CSRF除外）
// ================================
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);


// ================================
// 認証不要ルート
// ================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/', fn() => redirect('/login'));

// LINEシフトログイン（従業員用）
Route::get('/shift/login/{line_user_id}', [ShiftController::class, 'loginWithLine'])->name('shift.login');


// ================================
// 認証必須ルート
// ================================
Route::middleware(['auth'])->group(function () {

    // ================================
    // ★ システム管理者ルート
    // ================================
    Route::middleware('can:super-admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

            // 会社管理
            Route::resource('companies', AdminCompanyController::class);

            // 会社別ユーザー絞り込み
            Route::get('users/by-company', [AdminUserController::class, 'byCompany'])
                ->name('users.byCompany');

            // ユーザー管理
            Route::resource('users', AdminUserController::class);
        });



    // ================================
    // ★ 会社管理者ルート
    // ================================
    Route::prefix('company/{company}')->name('company.')->group(function () {

        // ダッシュボード
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('dashboard');
        Route::put('/', [CompanyController::class, 'update'])->name('update');
        Route::get('/recent-logs', [CompanyController::class, 'recentLogs'])->name('recentLogs');


        // -----------------------------
        // 店舗管理
        // -----------------------------
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::get('/create', [StoreController::class, 'create'])->name('create');
            Route::post('/', [StoreController::class, 'store'])->name('store');
            Route::get('/{store}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::put('/{store}', [StoreController::class, 'update'])->name('update');
            Route::delete('/{store}', [StoreController::class, 'destroy'])->name('destroy');
        });


        // -----------------------------
        // 社員管理
        // -----------------------------
        Route::get('/employees', [EmployeeController::class, 'employees'])->name('employees');
        Route::get('/employees/create', [EmployeeController::class, 'createEmployee'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'storeEmployee'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'editEmployee'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'deleteEmployee'])->name('employees.delete');
        Route::put('/employees/{employee}/update-wage', [EmployeeController::class, 'updateWage'])->name('employees.updateWage');


            // ==============================
        // 勤怠管理
        // ==============================
        Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances');
        Route::get('/attendances/create', [AttendanceController::class, 'createAttendance'])->name('attendances.create');
        Route::post('/attendances', [AttendanceController::class, 'storeAttendance'])->name('attendances.store');
        Route::get('/attendances/{attendance}/edit', [AttendanceController::class, 'editAttendance'])->name('attendances.edit');
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'updateAttendance'])->name('attendances.update');
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroyAttendance'])->name('attendances.destroy');
        Route::get('/attendances/late-early', [AttendanceController::class, 'lateEarlyList'])->name('attendances.late_early');




        // -----------------------------
        // 給与管理
        // -----------------------------
        Route::get('/payrolls', [PayrollController::class, 'payrolls'])->name('payrolls');
        Route::get('/payrolls/csv', [PayrollController::class, 'payrollsCsv'])->name('payrollsCsv');
        Route::get('/payrolls/pdf', [PayrollController::class, 'payrollsPdf'])->name('payrollsPdf');
        Route::post('/payrolls/recalculate', [PayrollController::class, 'recalculate'])->name('payrolls.recalculate');


        // -----------------------------
        // シフト承認フロー
   Route::prefix('shift')->name('shift.')->group(function () {

    // 提出シフト一覧
    Route::get('/requests', [ShiftApprovalController::class, 'index'])
        ->name('requests');

    // 個別承認
    Route::post('/requests/{shiftRequest}/approve', [ShiftApprovalController::class, 'approve'])
        ->name('requests.approve');

    // 差戻し
    Route::post('/requests/{shiftRequest}/reject', [ShiftApprovalController::class, 'reject'])
        ->name('requests.reject');

    // 一括承認
    Route::post('/requests/approve-all', [ShiftApprovalController::class, 'approveAll'])
        ->name('requests.approveAll');

    // カレンダー編集
    Route::get('/edit', [ShiftApprovalController::class, 'calendar'])
        ->name('calendar.edit');
    Route::post('/edit', [ShiftApprovalController::class, 'calendarSave'])
        ->name('calendar.save');

    // 保存
    Route::post('/save', [ShiftApprovalController::class, 'save'])
        ->name('save');

    // 削除ページ
    Route::get('/delete', [ShiftApprovalController::class, 'deletePage'])
        ->name('delete.page');

    // 削除実行
    Route::delete('/delete', [ShiftApprovalController::class, 'delete'])
        ->name('delete');

    // 日付別取得
    Route::get('/requests/{date}', [ShiftApprovalController::class, 'getRequestsByDate'])
        ->name('requests.by_date');

    // シフト詳細
    Route::get('/{id}', [ShiftApprovalController::class, 'show'])
        ->name('show');

    // 削除
    Route::delete('/{id}', [ShiftApprovalController::class, 'destroy'])
        ->name('destroy');
});

    });


    // -----------------------------
    // 従業員用シフト提出
    // -----------------------------
    Route::prefix('shift')->name('shift.user.')->group(function () {
        Route::get('/calendar/{user}', [ShiftController::class, 'calendar'])->name('calendar');
        Route::get('/events/{user}', [ShiftController::class, 'events'])->name('events');
        Route::post('/save', [ShiftController::class, 'save'])->name('save');
        Route::post('/save-all', [ShiftController::class, 'saveAll'])->name('saveAll');
        Route::delete('/{shift}', [ShiftController::class, 'delete'])->name('delete');
    });
});

// ================================
// 勤怠管理（休憩終了ボタン用）
// ================================
Route::middleware(['auth'])->group(function () {
    Route::prefix('attendance')->name('attendance.')->group(function () {
        // 休憩終了ボタン
        Route::post('/break-end/{attendance}', [AttendanceController::class, 'breakEnd'])
            ->name('breakEnd');
    });
});
