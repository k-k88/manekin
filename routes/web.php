<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\LineWebhookController;

// 🔹 LINE Webhook（CSRF除外）
Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);

// 🔹 ログイン機能
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 🔹 企業管理画面（認証必須）
Route::middleware(['auth'])->group(function () {
    Route::prefix('company/{company}')->group(function () {
        Route::get('/dashboard', [CompanyController::class, 'dashboard'])->name('company.dashboard');
        Route::get('/employees', [CompanyController::class, 'employees'])->name('company.employees');
        Route::get('/attendances', [CompanyController::class, 'attendances'])->name('company.attendances');
    });
});

// 🔹 ホーム画面（任意）
Route::get('/', function () {
    return redirect('/login');
    
});
Route::get('/company/{company}/employees', [App\Http\Controllers\CompanyController::class, 'employees'])
    ->name('company.employees');
    
Route::get('/company/{company}/attendances', [App\Http\Controllers\CompanyController::class, 'attendances'])
    ->name('company.attendances');

