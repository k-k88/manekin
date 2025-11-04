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
    Schema::table('companies', function (Blueprint $table) {
        $table->integer('closing_day')->default(25)->comment('締め日'); // 1〜31
    });
}

public function down()
{
    Schema::table('companies', function (Blueprint $table) {
        $table->dropColumn('closing_day');
    });
}

};
