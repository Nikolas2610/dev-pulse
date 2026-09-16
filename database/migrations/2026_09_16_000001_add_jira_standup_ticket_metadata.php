<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standup_questions', function (Blueprint $table) {
            $table->string('work_item_relation')->nullable()->after('question_key');
        });

        Schema::table('jira_projects', function (Blueprint $table) {
            $table->json('statuses_json')->nullable()->after('avatar_url');
        });

        Schema::table('workspace_jira_project', function (Blueprint $table) {
            $table->json('included_status_ids')->nullable()->after('jira_project_id');
        });

        Schema::table('external_work_items', function (Blueprint $table) {
            $table->string('external_status_id')->nullable()->after('status');
        });

        Schema::table('standup_response_items', function (Blueprint $table) {
            $table->dropUnique('standup_response_work_item_unique');
            $table->foreignId('standup_question_id')->nullable()->after('standup_response_id')->constrained()->nullOnDelete();
            $table->unique(
                ['standup_response_id', 'external_work_item_id', 'relation'],
                'standup_response_work_item_relation_unique',
            );
        });

        DB::table('standup_questions')
            ->whereNull('work_item_relation')
            ->where('question_key', 'yesterday')
            ->update(['work_item_relation' => 'worked_on']);
        DB::table('standup_questions')
            ->whereNull('work_item_relation')
            ->where('question_key', 'today')
            ->update(['work_item_relation' => 'planned']);
        DB::table('standup_questions')
            ->whereNull('work_item_relation')
            ->where('question_key', 'blocker_detail')
            ->update(['work_item_relation' => 'blocker']);
    }

    public function down(): void
    {
        Schema::table('standup_response_items', function (Blueprint $table) {
            $table->dropUnique('standup_response_work_item_relation_unique');
            $table->dropConstrainedForeignId('standup_question_id');
            $table->unique(['standup_response_id', 'external_work_item_id'], 'standup_response_work_item_unique');
        });

        Schema::table('external_work_items', fn (Blueprint $table) => $table->dropColumn('external_status_id'));
        Schema::table('workspace_jira_project', fn (Blueprint $table) => $table->dropColumn('included_status_ids'));
        Schema::table('jira_projects', fn (Blueprint $table) => $table->dropColumn('statuses_json'));
        Schema::table('standup_questions', fn (Blueprint $table) => $table->dropColumn('work_item_relation'));
    }
};
