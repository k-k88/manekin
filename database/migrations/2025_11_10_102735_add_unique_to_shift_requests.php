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
    Schema::table('shift_requests', function (Blueprint $table) {
        $table->unique(['user_id', 'shift_date'], 'user_shift_request_unique');
    });
}

public function down()
{
    Schema::table('shift_requests', function (Blueprint $table) {
        $table->dropUnique('user_shift_request_unique');
    });
}

    };
