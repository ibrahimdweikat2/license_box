<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LicenseController extends ApiController
{
    public function checkConnection(Request $request): Response
    {
        return $this->respond($request, true, 'Connection successful.', [
            'server' => Setting::get('site_name'),
            'api_version' => 'v1',
            'key_id' => $this->signer->fingerprint(),
        ]);
    }

    public function activate(Request $request): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['required', 'string', 'max:64'],
            'client_name' => ['nullable', 'string', 'max:191'],
            'domain' => ['nullable', 'string', 'max:191'],
        ];

        return $this->handle($request, 'activate', $rules, function (array $in, array &$context) use ($request) {
            $product = $this->product($request, $in['product_id']);
            $license = $this->license($product, $in['license_key'], $context);

            $data = $this->licenses->activate($product, $license->license_key, $in['domain'] ?? null, $request->ip(), $in['client_name'] ?? null);

            return ['License activated successfully.', $data];
        });
    }

    public function verify(Request $request): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:191'],
        ];

        return $this->handle($request, 'verify', $rules, function (array $in, array &$context) use ($request) {
            $product = $this->product($request, $in['product_id']);
            $license = $this->license($product, $in['license_key'], $context);

            $data = $this->licenses->verify($product, $license->license_key, $in['instance_id'], $in['domain'] ?? null, $request->ip());

            return ['License is valid.', $data];
        });
    }

    public function deactivate(Request $request): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:64'],
        ];

        return $this->handle($request, 'deactivate', $rules, function (array $in, array &$context) use ($request) {
            $product = $this->product($request, $in['product_id']);
            $license = $this->license($product, $in['license_key'], $context);

            $this->licenses->deactivate($product, $license->license_key, $in['instance_id']);

            return ['License deactivated successfully.', [
                'activations_used' => $license->activeActivations()->count(),
                'max_activations' => $license->max_activations,
            ]];
        });
    }
}
