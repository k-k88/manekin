<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->decimal('hourly_wage', 8, 2)->after('clock_out')->default(0);
    });
}

public function down(): void
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->dropColumn('hourly_wage');
    });
}

};
