<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'wage_updated_at')) {
                $table->timestamp('wage_updated_at')->nullable()->after('hourly_wage');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wage_updated_at')) {
                $table->dropColumn('wage_updated_at');
            }
        });
    }
};
