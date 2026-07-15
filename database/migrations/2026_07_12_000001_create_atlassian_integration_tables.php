<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('project_key');
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'external_id']);
        });

        Schema::create('bitbucket_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('workspace_uuid');
            $table->string('workspace_slug');
            $table->string('repository_uuid');
            $table->string('repository_slug');
            $table->string('full_name');
            $table->string('project_name')->nullable();
            $table->string('main_branch')->nullable();
            $table->boolean('is_private')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'repository_uuid']);
        });

        Schema::create('workspace_jira_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jira_project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'jira_project_id']);
        });

        Schema::create('workspace_bitbucket_repository', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bitbucket_repository_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'bitbucket_repository_id'], 'workspace_bitbucket_repo_unique');
        });

        Schema::create('bitbucket_repository_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bitbucket_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['bitbucket_repository_id', 'user_id'], 'bitbucket_repo_user_unique');
        });

        Schema::create('external_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jira_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bitbucket_repository_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_integration_user_id')->nullable()->constrained('integration_users')->nullOnDelete();
            $table->foreignId('author_integration_user_id')->nullable()->constrained('integration_users')->nullOnDelete();
            $table->string('provider');
            $table->string('external_id');
            $table->string('external_key')->nullable();
            $table->string('type');
            $table->text('title');
            $table->text('url');
            $table->string('status');
            $table->string('priority')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->unsignedInteger('task_count')->default(0);
            $table->json('reviewer_external_ids')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'integration_id', 'external_id']);
            $table->index(['workspace_id', 'type', 'status']);
        });

        Schema::create('standup_response_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standup_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_work_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relation');
            $table->string('provider');
            $table->string('external_key')->nullable();
            $table->text('title_snapshot');
            $table->string('status_snapshot');
            $table->text('url_snapshot');
            $table->timestamps();
            $table->unique(['standup_response_id', 'external_work_item_id'], 'standup_response_work_item_unique');
        });

        Schema::create('integration_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('delivery_id');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration_id', 'delivery_id']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedSmallInteger('stale_pr_days')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('stale_pr_days'));
        Schema::dropIfExists('integration_webhook_deliveries');
        Schema::dropIfExists('standup_response_items');
        Schema::dropIfExists('external_work_items');
        Schema::dropIfExists('bitbucket_repository_user');
        Schema::dropIfExists('workspace_bitbucket_repository');
        Schema::dropIfExists('workspace_jira_project');
        Schema::dropIfExists('bitbucket_repositories');
        Schema::dropIfExists('jira_projects');
    }
};
