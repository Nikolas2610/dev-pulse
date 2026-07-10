<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_required')->default(false)->after('is_active');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('onboarding_step')->nullable()->after('is_active');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_step');
        });

        DB::table('organizations')->update([
            'onboarding_step' => 'complete',
            'onboarding_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'onboarding_completed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_required');
        });
    }
};
