<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wage_histories', function (Blueprint $table) {
            // 有効終了日カラムを追加
            $table->date('end_date')->nullable()->after('effective_from');
        });
    }

    public function down(): void
    {
        Schema::table('wage_histories', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
