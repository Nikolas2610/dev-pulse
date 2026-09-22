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
            $table->json('work_item_providers')->nullable()->after('work_item_relation');
        });

        DB::table('standup_questions')
            ->whereNotNull('work_item_relation')
            ->update(['work_item_providers' => json_encode(['jira'])]);
    }

    public function down(): void
    {
        Schema::table('standup_questions', function (Blueprint $table) {
            $table->dropColumn('work_item_providers');
        });
    }
};
