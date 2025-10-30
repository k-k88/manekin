<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('wage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('hourly_wage', 8, 2);
            $table->date('effective_from'); // この日以降に有効
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wage_histories');
    }
};

