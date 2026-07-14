<?php

namespace App\Services;

use App\Models\AutomationExecution;
use Closure;
use Throwable;

class AutomationAuditService
{
    /**
     * @param  Closure(): (array<string, mixed>|null)  $callback
     * @return array<string, mixed>|null
     */
    public function run(string $task, Closure $callback): ?array
    {
        $startedAt = now();
        $execution = AutomationExecution::create([
            'task' => $task,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $metrics = $callback();
            $execution->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
                'metrics_json' => $metrics,
            ]);

            return $metrics;
        } catch (Throwable $exception) {
            $execution->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
                'error' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw $exception;
        }
    }

    public function heartbeat(): void
    {
        $this->run('scheduler.heartbeat', fn (): array => ['server_time' => now()->toIso8601String()]);

        AutomationExecution::query()->where('started_at', '<', now()->subDays(14))->delete();
    }
}
