<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('shifts', function (Blueprint $table) {
            // 既存データを保持したまま DATETIME → TIME に変換
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('shifts', function (Blueprint $table) {
            // 元に戻せるように（戻す可能性は低い）
            $table->dateTime('start_time')->nullable()->change();
            $table->dateTime('end_time')->nullable()->change();
        });
    }
};
