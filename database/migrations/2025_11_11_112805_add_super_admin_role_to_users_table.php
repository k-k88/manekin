<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // ENUM追加
        DB::statement("
            ALTER TABLE users 
            MODIFY role ENUM('employee', 'company_admin', 'super_admin') 
            NOT NULL DEFAULT 'employee';
        ");
    }

    public function down()
    {
        // 元に戻す（super_admin を削除）
        DB::statement("
            ALTER TABLE users 
            MODIFY role ENUM('employee', 'company_admin') 
            NOT NULL DEFAULT 'employee';
        ");
    }
};
