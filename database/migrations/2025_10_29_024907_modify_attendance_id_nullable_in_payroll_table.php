<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            // 外部キー制約を一時削除
            $table->dropForeign(['attendance_id']);
            
            // nullable に変更
            $table->unsignedBigInteger('attendance_id')->nullable()->change();

            // 外部キーを再作成
            $table->foreign('attendance_id')->references('id')->on('attendance')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
            $table->unsignedBigInteger('attendance_id')->nullable(false)->change();
            $table->foreign('attendance_id')->references('id')->on('attendance')->onDelete('cascade');
        });
    }
};
