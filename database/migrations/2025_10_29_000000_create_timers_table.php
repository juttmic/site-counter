<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('timer_type', ['fixed_date', 'reload_based']);
            $table->dateTime('target_datetime')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('skin');
            $table->enum('text_position', ['top', 'bottom', 'none'])->default('none');
            $table->string('custom_text')->nullable();
            $table->string('gif_url');
            $table->string('cache_key')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timers');
    }
};


