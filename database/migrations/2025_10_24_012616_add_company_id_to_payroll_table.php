<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            // ✅ company_id カラムが存在しない場合のみ追加
            if (!Schema::hasColumn('payroll', 'company_id')) {
                $table->unsignedBigInteger('company_id')->after('user_id');

                // ✅ 外部キーを company_id のみに限定
                $table->foreign('company_id')
                      ->references('id')->on('companies')
                      ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            if (Schema::hasColumn('payroll', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};
