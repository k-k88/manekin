<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable(); // ← 追加（どの会社の社員か）
            $table->string('name');
            $table->string('line_user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->enum('role', ['staff', 'manager', 'admin'])->default('staff');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // 外部キー制約（あとでcompanies/storesを作ってもOK）
            $table->foreign('company_id')
                  ->references('id')->on('companies')
                  ->onDelete('cascade');
            $table->foreign('store_id')
                  ->references('id')->on('stores')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
