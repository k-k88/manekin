<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('shift_requests', function (Blueprint $table) {
        $table->unsignedBigInteger('store_id')->nullable()->after('user_id');
    });
}

public function down()
{
    Schema::table('shift_requests', function (Blueprint $table) {
        $table->dropColumn('store_id');
    });
}

};
