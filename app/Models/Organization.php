<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone', 'is_active', 'onboarding_step', 'onboarding_completed_at'])]
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Workspace, $this> */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** @return HasMany<StandupTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(StandupTemplate::class);
    }
}
