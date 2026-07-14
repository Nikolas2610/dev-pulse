<?php

namespace App\Jobs;

use App\Services\ManagerBlockerAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendManagerBlockerAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $standupResponseId) {}

    public function handle(ManagerBlockerAlertService $alerts): void
    {
        Cache::lock('manager-blocker-alert:'.$this->standupResponseId, 300)
            ->block(5, fn () => $alerts->sendForResponse($this->standupResponseId));
    }
}
