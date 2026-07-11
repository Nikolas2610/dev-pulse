<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['standup_response_id', 'standup_question_id', 'answer_text', 'answer_json'])]
/**
 * @property int $id
 * @property int $standup_response_id
 * @property int $standup_question_id
 * @property string|null $answer_text
 * @property array<string, mixed>|null $answer_json
 */
class StandupAnswer extends Model
{
    protected function casts(): array
    {
        return ['answer_json' => 'array'];
    }

    /** @return BelongsTo<StandupResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(StandupResponse::class, 'standup_response_id');
    }

    /** @return BelongsTo<StandupQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(StandupQuestion::class, 'standup_question_id');
    }
}
