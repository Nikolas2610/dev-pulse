<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standup_questions', function (Blueprint $table) {
            $table->foreignId('depends_on_question_id')
                ->nullable()
                ->after('options_json')
                ->constrained('standup_questions')
                ->nullOnDelete();
            $table->string('depends_on_operator')->nullable()->after('depends_on_question_id');
            $table->string('depends_on_value')->nullable()->after('depends_on_operator');
        });
    }

    public function down(): void
    {
        Schema::table('standup_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depends_on_question_id');
            $table->dropColumn(['depends_on_operator', 'depends_on_value']);
        });
    }
};
