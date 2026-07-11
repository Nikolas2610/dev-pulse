<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['integration_id', 'external_channel_id', 'name', 'type', 'raw_json', 'is_active'])]
/**
 * @property int $id
 * @property int $integration_id
 * @property string $external_channel_id
 * @property string $name
 * @property string|null $type
 * @property bool $is_active
 */
class IntegrationChannel extends Model
{
    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
