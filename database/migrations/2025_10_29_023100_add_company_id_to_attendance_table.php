<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->unsignedBigInteger('company_id')->after('store_id');

        // もし companies テーブルとの外部キー制約を付けたい場合
        // $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('attendance', function (Blueprint $table) {
        $table->dropColumn('company_id');
        // $table->dropForeign(['company_id']); // 外部キーの場合
    });
}

};
