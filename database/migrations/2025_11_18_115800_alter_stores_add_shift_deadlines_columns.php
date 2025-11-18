<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // enum('single') → string へ変更（ENUMは柔軟性が低いため）
            $table->string('shift_deadline_type', 10)->default('single')->change();

            // 提出期限フィールドが無ければ追加
            if (!Schema::hasColumn('stores', 'shift_deadline_day')) {
                $table->unsignedTinyInteger('shift_deadline_day')->nullable()->after('shift_deadline_type');
            }
            if (!Schema::hasColumn('stores', 'shift_first_half_deadline')) {
                $table->unsignedTinyInteger('shift_first_half_deadline')->nullable()->after('shift_deadline_day');
            }
            if (!Schema::hasColumn('stores', 'shift_second_half_deadline')) {
                $table->unsignedTinyInteger('shift_second_half_deadline')->nullable()->after('shift_first_half_deadline');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->enum('shift_deadline_type', ['single'])->default('single')->change();
        });
    }
};
