<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->time('clock_in')->change();
            $table->time('clock_out')->change();
            $table->time('break_start')->nullable()->change();
            $table->time('break_end')->nullable()->change();  // ← ここを追加
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dateTime('clock_in')->change();
            $table->dateTime('clock_out')->change();
            $table->dateTime('break_start')->nullable()->change();
            $table->dateTime('break_end')->nullable()->change(); // ← ここを戻す
        });
    }
};
