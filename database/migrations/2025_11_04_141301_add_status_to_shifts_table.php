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
        $table->enum('status', ['pending', 'approved', 'rejected'])
              ->default('pending')
              ->after('is_day_off');
    });
}

public function down()
{
    Schema::table('shifts', function (Blueprint $table) {
        $table->dropColumn('status');
    });
}

};
