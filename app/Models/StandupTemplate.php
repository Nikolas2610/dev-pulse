<?php

namespace App\Models;

use Database\Factories\StandupTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'workspace_id',
    'name',
    'description',
    'is_default',
    'is_active',
    'created_by_user_id',
])]
/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $workspace_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 */
class StandupTemplate extends Model
{
    /** @use HasFactory<StandupTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<StandupQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(StandupQuestion::class)->orderBy('sort_order');
    }
}
