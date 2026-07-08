<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->text('signing_secret')->nullable()->after('client_secret');
        });

        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->foreignId('prompt_channel_id')
                ->nullable()
                ->after('target_integration_id')
                ->constrained('integration_channels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('standup_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prompt_channel_id');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('signing_secret');
        });
    }
};
