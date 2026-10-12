<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\License;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * All license rules live here so the API and the admin panel behave identically.
 */
class LicenseService
{
    public function __construct(private ResponseSigner $signer) {}

    public function findProduct(string $productUid): Product
    {
        $product = Product::where('product_uid', $productUid)->first();

        if (! $product) {
            throw new LicenseException('invalid_product', 'Unknown product.', 404);
        }

        if (! $product->isActive()) {
            throw new LicenseException('product_inactive', 'This product is not available.', 403);
        }

        return $product;
    }

    public function findLicense(Product $product, string $licenseKey): License
    {
        $license = License::where('product_id', $product->id)
            ->where('license_key', strtoupper(trim($licenseKey)))
            ->first();

        if (! $license) {
            throw new LicenseException('invalid_license', 'Invalid license key.', 404);
        }

        return $license;
    }

    /**
     * Throw if the license cannot be used right now (status or expiry).
     */
    public function assertUsable(License $license): void
    {
        if ($license->status === 'active' && $license->isExpired()) {
            $license->update(['status' => 'expired']);
        }

        match ($license->status) {
            'active' => null,
            'suspended' => throw new LicenseException('license_suspended', 'This license has been suspended.', 403),
            'revoked' => throw new LicenseException('license_revoked', 'This license has been revoked.', 403),
            'expired' => throw new LicenseException('license_expired', 'This license has expired.', 403),
            default => throw new LicenseException('invalid_license', 'Invalid license status.', 403),
        };
    }

    /**
     * Activate a license for a domain / IP. Re-activating the same domain (or IP when no
     * domain is sent) reuses the existing slot instead of consuming a new one.
     */
    public function activate(Product $product, string $licenseKey, ?string $domain, ?string $ip, ?string $clientName = null): array
    {
        $license = $this->findLicense($product, $licenseKey);
        $this->assertUsable($license);

        $domain = self::normalizeDomain($domain);
        $this->assertDomainAllowed($license, $domain);
        $this->assertIpAllowed($license, $ip);

        $activation = DB::transaction(function () use ($license, $domain, $ip, $clientName) {
            // Lock the license row so concurrent activations cannot exceed the limit.
            License::whereKey($license->id)->lockForUpdate()->first();

            $existing = $license->activeActivations()
                ->when($domain, fn ($q) => $q->where('domain', $domain), fn ($q) => $q->whereNull('domain')->where('ip', $ip))
                ->first();

            if ($existing) {
                $existing->update(['ip' => $ip, 'client_name' => $clientName ?? $existing->client_name, 'last_checked_at' => now()]);

                return $existing;
            }

            $limit = $license->max_activations;
            if ($limit > 0 && $license->activeActivations()->count() >= $limit) {
                throw new LicenseException('activation_limit', "Activation limit reached ($limit). Deactivate another installation first.", 403);
            }

            return $license->activations()->create([
                'domain' => $domain,
                'ip' => $ip,
                'client_name' => $clientName,
                'instance_id' => Str::lower(Str::random(32)),
                'activated_at' => now(),
                'last_checked_at' => now(),
                'is_active' => true,
            ]);
        });

        return $this->payload($license, $activation);
    }

    public function verify(Product $product, string $licenseKey, string $instanceId, ?string $domain, ?string $ip): array
    {
        $license = $this->findLicense($product, $licenseKey);
        $activation = $this->findActivation($license, $instanceId);
        $this->assertUsable($license);

        $domain = self::normalizeDomain($domain);
        if ($activation->domain !== null && $domain !== null && $activation->domain !== $domain) {
            throw new LicenseException('domain_mismatch', 'This activation belongs to a different domain.', 403);
        }

        $this->assertDomainAllowed($license, $activation->domain);
        $this->assertIpAllowed($license, $ip);

        $activation->update(['last_checked_at' => now(), 'ip' => $ip ?? $activation->ip]);

        return $this->payload($license, $activation);
    }

    public function deactivate(Product $product, string $licenseKey, string $instanceId): Activation
    {
        $license = $this->findLicense($product, $licenseKey);
        $activation = $this->findActivation($license, $instanceId);
        $activation->update(['is_active' => false]);

        return $activation;
    }

    /**
     * Resolve an active activation of a license (used by the update endpoints too).
     */
    public function findActivation(License $license, string $instanceId): Activation
    {
        $activation = $license->activations()->where('instance_id', $instanceId)->first();

        if (! $activation || ! $activation->is_active) {
            throw new LicenseException('invalid_activation', 'This installation is not activated. Please activate the license again.', 403);
        }

        return $activation;
    }

    public function assertDomainAllowed(License $license, ?string $domain): void
    {
        $patterns = array_filter((array) $license->allowed_domains);
        if ($patterns === []) {
            return;
        }

        if ($domain === null || ! self::matchesAny($domain, array_map([self::class, 'normalizeDomain'], $patterns))) {
            throw new LicenseException('domain_not_allowed', 'This license is not valid for this domain.', 403);
        }
    }

    public function assertIpAllowed(License $license, ?string $ip): void
    {
        $patterns = array_filter((array) $license->allowed_ips);
        if ($patterns === []) {
            return;
        }

        if ($ip === null || ! self::matchesAny($ip, $patterns)) {
            throw new LicenseException('ip_not_allowed', 'This license is not valid for this IP address.', 403);
        }
    }

    /**
     * "https://www.Example.com:8080/path" -> "example.com"
     */
    public static function normalizeDomain(?string $domain): ?string
    {
        $domain = strtolower(trim((string) $domain));
        if ($domain === '') {
            return null;
        }

        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
        $domain = preg_replace('#[/?\#].*$#', '', $domain);
        $domain = preg_replace('#:\d+$#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);

        return $domain === '' ? null : $domain;
    }

    /**
     * Patterns support "*" wildcards: "*.example.com" also matches "example.com" itself.
     */
    private static function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }
            if (Str::is($pattern, $value) || (str_starts_with($pattern, '*.') && $value === substr($pattern, 2))) {
                return true;
            }
        }

        return false;
    }

    private function payload(License $license, Activation $activation): array
    {
        $nextCheck = now()->addDays((int) Setting::get('verify_interval_days'));
        $validUntil = $nextCheck->copy()->addDays((int) Setting::get('offline_grace_days'));

        if ($license->expires_at && $license->expires_at->lt($validUntil)) {
            $validUntil = $license->expires_at->copy();
        }

        $localKey = $this->signer->token([
            'license_hash' => hash('sha256', $license->license_key),
            'product_id' => $license->product->product_uid,
            'instance_id' => $activation->instance_id,
            'domain' => $activation->domain,
            'issued_at' => now()->timestamp,
            'next_check_at' => $nextCheck->timestamp,
            'valid_until' => $validUntil->timestamp,
        ]);

        return [
            'instance_id' => $activation->instance_id,
            'license_type' => $license->type,
            'client_name' => $activation->client_name,
            'domain' => $activation->domain,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'updates_expire_at' => $license->updates_expire_at?->toIso8601String(),
            'activations_used' => $license->activeActivations()->count(),
            'max_activations' => $license->max_activations,
            'next_check_at' => $nextCheck->toIso8601String(),
            'local_key' => $localKey,
        ];
    }
}
