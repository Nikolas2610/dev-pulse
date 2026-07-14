<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->foreignId('manager_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->boolean('notify_manager_on_blocker')->default(false)->after('target_channel_id');
        });

        Schema::create('manager_blocker_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standup_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_message_id')->unique();
            $table->string('status')->default('pending');
            $table->string('external_message_id')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['standup_response_id', 'manager_user_id', 'integration_id'],
                'manager_blocker_delivery_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_blocker_notifications');

        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->dropColumn('notify_manager_on_blocker');
        });

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_user_id');
        });
    }
};
