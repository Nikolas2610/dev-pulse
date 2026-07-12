<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $task
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $metrics_json
 * @property string|null $error
 */
#[Fillable(['task', 'status', 'started_at', 'finished_at', 'duration_ms', 'metrics_json', 'error'])]
class AutomationExecution extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metrics_json' => 'array',
        ];
    }
}
