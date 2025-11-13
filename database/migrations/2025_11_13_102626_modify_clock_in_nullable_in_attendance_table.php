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
        $table->time('clock_in')->nullable()->change();
        $table->time('clock_out')->nullable()->change(); // 同様に
    });
}

public function down(): void
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->time('clock_in')->nullable(false)->change();
        $table->time('clock_out')->nullable(false)->change();
    });
}

};
