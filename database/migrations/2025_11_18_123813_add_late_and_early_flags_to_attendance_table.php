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
        $table->boolean('late_flag')->default(false)->after('clock_in');
        $table->boolean('early_leave_flag')->default(false)->after('clock_out');
    });
}

public function down(): void
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->dropColumn(['late_flag', 'early_leave_flag']);
    });
}

};
