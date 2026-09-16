<?php

namespace Tests\Feature\DevPulse;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JiraStandupMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jira_metadata_migration_can_resume_after_schema_changes_were_already_applied(): void
    {
        $migration = '2026_09_16_000001_add_jira_standup_ticket_metadata';

        $this->assertDatabaseHas('migrations', ['migration' => $migration]);

        DB::table('migrations')->where('migration', $migration)->delete();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('migrations', ['migration' => $migration]);
        $this->assertTrue(Schema::hasColumn('standup_questions', 'work_item_relation'));
        $this->assertTrue(Schema::hasColumn('jira_projects', 'statuses_json'));
        $this->assertTrue(Schema::hasColumn('workspace_jira_project', 'included_status_ids'));
        $this->assertTrue(Schema::hasColumn('external_work_items', 'external_status_id'));
        $this->assertTrue(Schema::hasColumn('standup_response_items', 'standup_question_id'));
        $this->assertTrue(Schema::hasIndex('standup_response_items', 'standup_response_work_item_relation_unique'));
    }
}
