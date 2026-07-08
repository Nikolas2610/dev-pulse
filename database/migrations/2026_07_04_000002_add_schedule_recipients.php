<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->string('recipient_mode')
                ->default('all_workspace_members')
                ->after('days_of_week');
        });

        Schema::create('standup_schedule_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standup_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['standup_schedule_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standup_schedule_user');

        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->dropColumn('recipient_mode');
        });
    }
};
