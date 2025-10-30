<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id'); // ✅ 追加
            $table->unsignedBigInteger('attendance_id'); // ✅ 明示追加
            $table->decimal('hourly_wage', 8, 2);
            $table->decimal('total_hours', 8, 2);
            $table->decimal('total_pay', 10, 2);
            $table->date('month');
            $table->timestamps();

            // 🔗 外部キー定義（順番OK）
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('attendance_id')->references('id')->on('attendance')->onDelete('cascade'); // ✅ 修正
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
