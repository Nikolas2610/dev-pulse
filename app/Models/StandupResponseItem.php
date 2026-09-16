<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $standup_response_id
 * @property int|null $standup_question_id
 * @property int|null $external_work_item_id
 * @property string $relation
 * @property string $provider
 * @property string|null $external_key
 * @property string $title_snapshot
 * @property string $status_snapshot
 * @property string $url_snapshot
 */
#[Fillable(['standup_response_id', 'standup_question_id', 'external_work_item_id', 'relation', 'provider', 'external_key', 'title_snapshot', 'status_snapshot', 'url_snapshot'])]
class StandupResponseItem extends Model
{
    /** @return BelongsTo<StandupResponse, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(StandupResponse::class, 'standup_response_id');
    }

    /** @return BelongsTo<ExternalWorkItem, $this> */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(ExternalWorkItem::class, 'external_work_item_id');
    }

    /** @return BelongsTo<StandupQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(StandupQuestion::class, 'standup_question_id');
    }
}
