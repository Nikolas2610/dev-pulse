<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->string('timezone')->nullable()->after('email_verified_at');
            $table->string('avatar_url')->nullable()->after('timezone');
            $table->boolean('is_active')->default(true)->after('avatar_url');
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->string('external_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('bot_token')->nullable();
            $table->json('settings_json')->nullable();
            $table->json('scopes_json')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'provider']);
        });

        Schema::create('integration_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('external_user_id');
            $table->string('external_username')->nullable();
            $table->string('external_email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'external_user_id']);
        });

        Schema::create('integration_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_channel_id');
            $table->string('name');
            $table->string('type');
            $table->json('raw_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['integration_id', 'external_channel_id']);
        });

        Schema::create('standup_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('standup_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standup_template_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_key')->nullable();
            $table->string('answer_type')->default('textarea');
            $table->json('options_json')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('standup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standup_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone');
            $table->time('time_of_day');
            $table->json('days_of_week');
            $table->unsignedInteger('reminder_minutes_after')->default(15);
            $table->unsignedInteger('summary_minutes_after')->default(30);
            $table->foreignId('target_integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            $table->foreignId('target_channel_id')->nullable()->constrained('integration_channels')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('standup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standup_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('standup_template_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status')->default('scheduled');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('summary_posted_at')->nullable();
            $table->timestamps();
            $table->unique(['standup_schedule_id', 'run_date']);
        });

        Schema::create('standup_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standup_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->string('source')->default('web');
            $table->string('external_message_id')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            $table->unique(['standup_run_id', 'user_id']);
        });

        Schema::create('standup_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standup_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standup_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->json('answer_json')->nullable();
            $table->timestamps();
        });

        Schema::create('standup_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('standup_run_id')->constrained()->cascadeOnDelete();
            $table->text('summary_text');
            $table->foreignId('posted_to_integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            $table->foreignId('posted_to_channel_id')->nullable()->constrained('integration_channels')->nullOnDelete();
            $table->string('external_message_id')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('event_type');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('standup_summaries');
        Schema::dropIfExists('standup_answers');
        Schema::dropIfExists('standup_responses');
        Schema::dropIfExists('standup_runs');
        Schema::dropIfExists('standup_schedules');
        Schema::dropIfExists('standup_questions');
        Schema::dropIfExists('standup_templates');
        Schema::dropIfExists('integration_channels');
        Schema::dropIfExists('integration_users');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('workspaces');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropColumn(['timezone', 'avatar_url', 'is_active']);
        });

        Schema::dropIfExists('organizations');
    }
};
