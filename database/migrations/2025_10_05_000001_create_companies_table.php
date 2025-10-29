<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 会社名
            $table->string('code')->unique(); // 管理コード（URLなどで使える）
            $table->timestamps();
            $table->decimal('default_hourly_wage', 8, 2)->nullable(); // 会社デフォルト時給

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
