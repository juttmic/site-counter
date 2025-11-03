<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->json('skin_overrides')->nullable()->after('skin');
            $table->json('skin_resolved_config')->nullable()->after('skin_overrides');
        });
    }

    public function down(): void
    {
        Schema::table('timers', function (Blueprint $table) {
            $table->dropColumn(['skin_overrides', 'skin_resolved_config']);
        });
    }
};


