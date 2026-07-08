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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(StandupQuestion::class)->orderBy('sort_order');
    }
}
