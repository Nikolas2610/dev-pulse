<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
