<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // 提出期限タイプ：single（一括） or split（前半後半）
            $table->enum('shift_deadline_type', ['single', 'split'])
                ->default('single');
             
                

            // 一括型（毎月◯日まで）
            $table->unsignedTinyInteger('shift_deadline_day')
                ->nullable()
                ->after('shift_deadline_type');

            // 分割型（前半・後半）
            $table->unsignedTinyInteger('shift_first_half_deadline')
                ->nullable()
                ->after('shift_deadline_day');
            $table->unsignedTinyInteger('shift_second_half_deadline')
                ->nullable()
                ->after('shift_first_half_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'shift_deadline_type',
                'shift_deadline_day',
                'shift_first_half_deadline',
                'shift_second_half_deadline',
            ]);
        });
    }
};
