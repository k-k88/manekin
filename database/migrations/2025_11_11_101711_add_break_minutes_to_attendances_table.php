<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            // 既存の break_minutes は残しつつ、開始／終了時刻を追加
            $table->dateTime('break_start')->nullable()->after('clock_out');
            $table->dateTime('break_end')->nullable()->after('break_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('break_end');
            $table->dropColumn('break_start');
        });
    }
};
