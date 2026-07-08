<?php

namespace App\Models;

use Database\Factories\StandupQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'standup_template_id',
    'question_text',
    'question_key',
    'answer_type',
    'options_json',
    'depends_on_question_id',
    'depends_on_operator',
    'depends_on_value',
    'is_required',
    'sort_order',
])]
class StandupQuestion extends Model
{
    /** @use HasFactory<StandupQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StandupTemplate::class, 'standup_template_id');
    }

    public function dependencyQuestion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_question_id');
    }

    public function standupTemplate(): BelongsTo
    {
        return $this->template();
    }
}
