<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_requests', function (Blueprint $table) {
            if (Schema::hasColumn('shift_requests', 'date')) {
                $table->dropColumn('date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shift_requests', function (Blueprint $table) {
            $table->date('date')->nullable();
        });
    }
};
