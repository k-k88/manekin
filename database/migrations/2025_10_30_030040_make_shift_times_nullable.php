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
    Schema::table('shifts', function (Blueprint $table) {
        $table->dateTime('start_time')->nullable()->change();
        $table->dateTime('end_time')->nullable()->change();
    });
}

public function down()
{
    Schema::table('shifts', function (Blueprint $table) {
        $table->dateTime('start_time')->nullable(false)->change();
        $table->dateTime('end_time')->nullable(false)->change();
    });
}
};