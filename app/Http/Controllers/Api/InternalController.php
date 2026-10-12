<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\License;
use App\Services\KeyGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Endpoints for trusted integrations (shop, billing system). Require an internal API key.
 */
class InternalController extends ApiController
{
    public function createLicense(Request $request, KeyGenerator $keys): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['nullable', 'string', 'max:64', 'unique:licenses,license_key'],
            'type' => ['nullable', Rule::in(License::TYPES)],
            'max_activations' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'expires_at' => ['nullable', 'date'],
            'updates_expire_at' => ['nullable', 'date'],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['string', 'max:191'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', 'max:64'],
            'client_name' => ['nullable', 'string', 'max:191'],
            'client_email' => ['nullable', 'email', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        return $this->handle($request, 'create_license', $rules, function (array $in, array &$context) use ($request, $keys) {
            $product = $context['product'] = $this->product($request, $in['product_id']);

            $client = null;
            if (! empty($in['client_email'])) {
                $client = Client::firstOrCreate(['email' => $in['client_email']], ['name' => $in['client_name'] ?? $in['client_email']]);
            } elseif (! empty($in['client_name'])) {
                $client = Client::create(['name' => $in['client_name']]);
            }

            $license = $context['license'] = $product->licenses()->create([
                'client_id' => $client?->id,
                'license_key' => isset($in['license_key']) ? strtoupper($in['license_key']) : $keys->unique(),
                'type' => $in['type'] ?? 'lifetime',
                'max_activations' => $in['max_activations'] ?? 1,
                'expires_at' => $in['expires_at'] ?? null,
                'updates_expire_at' => $in['updates_expire_at'] ?? null,
                'allowed_domains' => $in['allowed_domains'] ?? null,
                'allowed_ips' => $in['allowed_ips'] ?? null,
                'notes' => $in['notes'] ?? null,
                'status' => 'active',
            ]);

            return ['License created.', $this->present($license)];
        });
    }

    public function getLicense(Request $request): Response
    {
        return $this->handle($request, 'get_license', self::keyRules(), function (array $in, array &$context) use ($request) {
            $license = $this->license($this->product($request, $in['product_id']), $in['license_key'], $context);

            return ['License found.', $this->present($license, withActivations: true)];
        });
    }

    public function blockLicense(Request $request): Response
    {
        return $this->setStatus($request, 'block_license', 'suspended', 'License suspended.');
    }

    public function unblockLicense(Request $request): Response
    {
        return $this->setStatus($request, 'unblock_license', 'active', 'License reactivated.');
    }

    private function setStatus(Request $request, string $action, string $status, string $message): Response
    {
        return $this->handle($request, $action, self::keyRules(), function (array $in, array &$context) use ($request, $status, $message) {
            $license = $this->license($this->product($request, $in['product_id']), $in['license_key'], $context);
            $license->update(['status' => $status]);

            return [$message, $this->present($license)];
        });
    }

    private static function keyRules(): array
    {
        return self::baseRules() + ['license_key' => ['required', 'string', 'max:64']];
    }

    private function present(License $license, bool $withActivations = false): array
    {
        $data = [
            'license_key' => $license->license_key,
            'product_id' => $license->product->product_uid,
            'type' => $license->type,
            'status' => $license->status,
            'max_activations' => $license->max_activations,
            'activations_used' => $license->activeActivations()->count(),
            'allowed_domains' => $license->allowed_domains,
            'allowed_ips' => $license->allowed_ips,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'updates_expire_at' => $license->updates_expire_at?->toIso8601String(),
            'client' => $license->client?->only(['name', 'email', 'company']),
            'created_at' => $license->created_at?->toIso8601String(),
        ];

        if ($withActivations) {
            $data['activations'] = $license->activations()->latest()->get()
                ->map(fn ($a) => $a->only(['instance_id', 'domain', 'ip', 'client_name', 'is_active']) + [
                    'activated_at' => $a->activated_at?->toIso8601String(),
                    'last_checked_at' => $a->last_checked_at?->toIso8601String(),
                ])->all();
        }

        return $data;
    }
}
