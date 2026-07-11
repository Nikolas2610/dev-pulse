<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordIntegrationService
{
    public function postWebhook(Integration $integration, string $content): ?string
    {
        if (! $integration->webhook_url) {
            return null;
        }

        $url = Str::contains($integration->webhook_url, '?')
            ? $integration->webhook_url.'&wait=true'
            : $integration->webhook_url.'?wait=true';

        $payload = Http::post($url, [
            'content' => $content,
            'allowed_mentions' => ['parse' => []],
        ])->throw()->json();

        return $payload['id'] ?? null;
    }
}
