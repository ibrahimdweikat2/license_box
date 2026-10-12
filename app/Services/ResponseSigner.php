<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Signs API responses and local activation keys with the server's RSA private key.
 * Clients verify with the public key embedded in the SDK, so they can detect
 * tampered responses or a fake license server without holding any secret.
 */
class ResponseSigner
{
    private ?string $privateKey = null;

    private ?string $publicKey = null;

    public function sign(string $data): string
    {
        if (! openssl_sign($data, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign data: '.openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * JSON API response signed over its raw body (X-LicenseBox-Signature header).
     * The client's "nonce" is echoed back so a recorded response cannot be replayed.
     */
    public function response(Request $request, bool $status, string $message, mixed $data = null, int $code = 200, ?string $reason = null): Response
    {
        $payload = ['status' => $status, 'message' => $message, 'data' => $data];
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }
        if (is_string($nonce = $request->input('nonce'))) {
            $payload['nonce'] = $nonce;
        }
        $payload['timestamp'] = time();

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($body, $code, [
            'Content-Type' => 'application/json',
            'X-LicenseBox-Signature' => $this->sign($body),
            'X-LicenseBox-Key-Id' => $this->fingerprint(),
        ]);
    }

    public function verify(string $data, string $signature): bool
    {
        $raw = base64_decode($signature, true);

        return $raw !== false && openssl_verify($data, $raw, $this->publicKey(), OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Build a self-contained signed token: base64(json payload) . "." . base64(signature).
     */
    public function token(array $payload): string
    {
        $body = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $body.'.'.$this->sign($body);
    }

    public function decodeToken(string $token): ?array
    {
        [$body, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! $this->verify($body, $signature)) {
            return null;
        }

        return json_decode(base64_decode($body), true);
    }

    public function publicKey(): string
    {
        $this->ensureKeys();

        return $this->publicKey ??= File::get($this->path('public.pem'));
    }

    public function fingerprint(): string
    {
        return substr(hash('sha256', $this->publicKey()), 0, 16);
    }

    /**
     * Create a fresh keypair. Existing SDK installations must be updated with the new public key.
     */
    public function regenerate(): void
    {
        $options = [
            'private_key_bits' => (int) config('licensebox.signing.bits', 2048),
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => resource_path('openssl/minimal.cnf'),
        ];

        $key = openssl_pkey_new($options);

        if ($key === false || ! openssl_pkey_export($key, $private, null, $options)) {
            throw new RuntimeException('Unable to generate RSA keypair: '.openssl_error_string());
        }

        File::ensureDirectoryExists(config('licensebox.signing.path'), 0700);
        File::put($this->path('private.pem'), $private);
        File::put($this->path('public.pem'), openssl_pkey_get_details($key)['key']);
        @chmod($this->path('private.pem'), 0600);

        $this->privateKey = $this->publicKey = null;
    }

    private function privateKey(): string
    {
        $this->ensureKeys();

        return $this->privateKey ??= File::get($this->path('private.pem'));
    }

    public function hasKeys(): bool
    {
        return File::exists($this->path('private.pem')) && File::exists($this->path('public.pem'));
    }

    /**
     * Outside production a missing keypair is created on first use. In production that would silently
     * break every SDK already shipped (they embed the old public key), so it is an error instead:
     * restore the keys from a backup, or create them once with `php artisan licensebox:install`.
     */
    private function ensureKeys(): void
    {
        if ($this->hasKeys()) {
            return;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('Signing keys not found in '.config('licensebox.signing.path').'. Restore them from a backup, or run `php artisan licensebox:install` on a new server.');
        }

        $this->regenerate();
    }

    public function path(string $file): string
    {
        return rtrim(config('licensebox.signing.path'), '/\\').DIRECTORY_SEPARATOR.$file;
    }
}
