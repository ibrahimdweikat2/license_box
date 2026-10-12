<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogger
{
    /** Request fields that are never written to the log. */
    private const HIDDEN = ['license_key', 'license_code', 'local_key', 'password', 'api_key'];

    public function log(
        Request $request,
        string $action,
        string $result,
        ?string $message = null,
        ?License $license = null,
        ?Product $product = null,
    ): ActivityLog {
        $payload = Arr::except($request->except(['_token']), self::HIDDEN);

        if ($key = $request->input('license_key', $request->input('license_code'))) {
            $payload['license_key'] = self::mask((string) $key);
        }

        return ActivityLog::create([
            'license_id' => $license?->id,
            'product_id' => $product?->id ?? $license?->product_id,
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'payload' => $payload ?: null,
            'result' => $result,
            'message' => $message ? Str::limit($message, 250, '') : null,
        ]);
    }

    /** "ABCD-EFGH-JKLM-NPQR" -> "ABCD-****-****-NPQR" */
    public static function mask(string $key): string
    {
        $parts = explode('-', $key);
        if (count($parts) > 2) {
            return implode('-', array_merge([reset($parts)], array_fill(0, count($parts) - 2, '****'), [end($parts)]));
        }

        return Str::mask($key, '*', 4, max(0, strlen($key) - 8));
    }
}
