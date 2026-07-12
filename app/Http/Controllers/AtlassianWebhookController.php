<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAtlassianIntegrationJob;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AtlassianWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, Integration $integration): Response
    {
        abort_unless(in_array($provider, ['jira', 'bitbucket'], true) && $integration->provider === $provider && $integration->is_active, 404);
        $secret = (string) data_get($integration->settings(), 'webhook_secret');
        abort_if($secret === '', 403, 'Webhook is not configured.');

        if ($provider === 'bitbucket') {
            $provided = (string) $request->header('X-Hub-Signature');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($expected, $provided), 403);
        } else {
            $provided = (string) ($request->header('X-DevPulse-Webhook-Secret') ?: $request->query('secret'));
            abort_unless(hash_equals($secret, $provided), 403);
        }

        $deliveryId = (string) ($request->header('X-Request-UUID') ?: $request->header('X-Atlassian-Webhook-Identifier') ?: hash('sha256', $request->getContent()));
        $inserted = DB::table('integration_webhook_deliveries')->insertOrIgnore([
            'integration_id' => $integration->id,
            'provider' => $provider,
            'delivery_id' => Str::limit($deliveryId, 255, ''),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted) {
            SyncAtlassianIntegrationJob::dispatch($integration->id);
        }

        return response('', 202);
    }
}
