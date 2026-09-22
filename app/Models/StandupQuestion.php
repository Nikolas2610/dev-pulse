<?php

namespace App\Models;

use Database\Factories\StandupQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $standup_template_id
 * @property string $question_text
 * @property string|null $question_key
 * @property string|null $work_item_relation
 * @property array<int, string>|null $work_item_providers
 * @property string $answer_type
 * @property array<int, string>|null $options_json
 * @property bool $is_required
 * @property int $sort_order
 */
#[Fillable([
    'standup_template_id',
    'question_text',
    'question_key',
    'work_item_relation',
    'work_item_providers',
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
            'work_item_providers' => 'array',
            'is_required' => 'boolean',
        ];
    }

    /** @return BelongsTo<StandupTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(StandupTemplate::class, 'standup_template_id');
    }

    /** @return BelongsTo<StandupQuestion, $this> */
    public function dependencyQuestion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_question_id');
    }

    /** @return BelongsTo<StandupTemplate, $this> */
    public function standupTemplate(): BelongsTo
    {
        return $this->template();
    }
}
