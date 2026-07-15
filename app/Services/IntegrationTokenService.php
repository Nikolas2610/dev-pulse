<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IntegrationTokenService
{
    public function accessToken(Integration $integration): string
    {
        $expiresAt = data_get($integration->settings(), 'token_expires_at');

        if (! $expiresAt || now()->addMinute()->lt($expiresAt)) {
            return (string) $integration->access_token;
        }

        return Cache::lock("integration-token:{$integration->id}", 20)->block(10, function () use ($integration): string {
            $integration->refresh();
            $expiresAt = data_get($integration->settings(), 'token_expires_at');
            if (! $expiresAt || now()->addMinute()->lt($expiresAt)) {
                return (string) $integration->access_token;
            }

            $provider = $integration->provider;
            $tokenUrl = $provider === 'jira'
                ? 'https://auth.atlassian.com/oauth/token'
                : 'https://bitbucket.org/site/oauth2/access_token';
            $request = Http::asForm();
            if ($provider === 'bitbucket') {
                $request = $request->withBasicAuth((string) config('services.bitbucket.client_id'), (string) config('services.bitbucket.client_secret'));
            }
            $payload = $request->post($tokenUrl, array_filter([
                'grant_type' => 'refresh_token',
                'refresh_token' => $integration->refresh_token,
                'client_id' => $provider === 'jira' ? config('services.jira.client_id') : null,
                'client_secret' => $provider === 'jira' ? config('services.jira.client_secret') : null,
            ]))->throw()->json();

            if (blank($payload['access_token'] ?? null)) {
                throw new RuntimeException("{$provider} did not return an access token.");
            }

            $settings = $integration->settings();
            $settings['token_expires_at'] = now()->addSeconds((int) ($payload['expires_in'] ?? 3600))->toIso8601String();
            $integration->forceFill([
                'access_token' => $payload['access_token'],
                'refresh_token' => $payload['refresh_token'] ?? $integration->refresh_token,
                'settings_json' => $settings,
            ])->save();

            return (string) $integration->access_token;
        });
    }
}
