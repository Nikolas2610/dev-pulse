<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->string('delivery_method')
                ->default('email')
                ->after('recipient_mode');
        });
    }

    public function down(): void
    {
        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};
