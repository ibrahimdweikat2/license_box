<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ResponseSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API calls with the X-API-KEY header.
 * Usage: "api.key" (external or internal keys) or "api.key:internal" (internal keys only).
 */
class VerifyApiKey
{
    public function handle(Request $request, Closure $next, string $type = 'external'): Response
    {
        $plain = (string) $request->header('X-API-KEY', '');
        $key = $plain !== '' ? ApiKey::findByPlain($plain) : null;

        if (! $key) {
            return $this->deny($request, 'invalid_api_key', 'Missing or invalid API key.', 401);
        }

        if ($type === 'internal' && $key->type !== 'internal') {
            return $this->deny($request, 'forbidden', 'This endpoint requires an internal API key.', 403);
        }

        // Only touch the column once a minute to avoid a write on every request.
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key', $key);

        return $next($request);
    }

    private function deny(Request $request, string $reason, string $message, int $status): Response
    {
        return app(ResponseSigner::class)->response($request, false, $message, null, $status, $reason);
    }
}
