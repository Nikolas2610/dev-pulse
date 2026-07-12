<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_work_items', function (Blueprint $table) {
            $table->dropUnique('external_work_items_provider_integration_id_external_id_unique');
            $table->unique(['provider', 'integration_id', 'external_id', 'workspace_id'], 'external_work_item_workspace_unique');
        });
    }

    public function down(): void
    {
        Schema::table('external_work_items', function (Blueprint $table) {
            $table->dropUnique('external_work_item_workspace_unique');
            $table->unique(['provider', 'integration_id', 'external_id']);
        });
    }
};
