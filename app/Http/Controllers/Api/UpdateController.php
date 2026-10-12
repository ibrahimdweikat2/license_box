<?php

namespace App\Http\Controllers\Api;

use App\Models\Activation;
use App\Models\ProductVersion;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UpdateController extends ApiController
{
    public function latestVersion(Request $request, UpdateService $updates): Response
    {
        return $this->handle($request, 'latest_version', self::baseRules(), function (array $in, array &$context) use ($request, $updates) {
            $product = $context['product'] = $this->product($request, $in['product_id']);
            $latest = $updates->latest($product);

            return [$latest ? 'Latest version found.' : 'No version has been released yet.', [
                'product' => $product->name,
                'latest_version' => $latest?->version,
                'release_date' => $latest?->release_date?->toDateString(),
                'changelog' => $latest?->changelog,
            ]];
        });
    }

    public function checkUpdate(Request $request, UpdateService $updates): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:64'],
            'current_version' => ['required', 'string', 'max:32'],
        ];

        return $this->handle($request, 'check_update', $rules, function (array $in, array &$context) use ($request, $updates) {
            $product = $this->product($request, $in['product_id']);
            $license = $this->license($product, $in['license_key'], $context);
            $this->licenses->findActivation($license, $in['instance_id']);
            $this->licenses->assertUsable($license);

            $data = $updates->check($product, $license, $in['current_version']);

            return [$data['update_available'] ? 'A new update is available.' : 'You are running the latest version.', $data];
        });
    }

    public function downloadUpdate(Request $request, UpdateService $updates): Response
    {
        $rules = self::baseRules() + [
            'license_key' => ['required', 'string', 'max:64'],
            'instance_id' => ['required', 'string', 'max:64'],
            'version' => ['required', 'string', 'max:32'],
        ];

        return $this->handle($request, 'download_update', $rules, function (array $in, array &$context) use ($request, $updates) {
            $product = $this->product($request, $in['product_id']);
            $license = $this->license($product, $in['license_key'], $context);
            $activation = $this->licenses->findActivation($license, $in['instance_id']);
            $this->licenses->assertUsable($license);

            return ['Download links generated.', $updates->downloadLinks($product, $license, $activation, $in['version'])];
        });
    }

    /**
     * Streams a release file. Reached only through the signed, expiring URL from downloadUpdate().
     */
    public function file(Request $request, ProductVersion $release, string $type, Activation $activation): StreamedResponse
    {
        $path = $type === 'sql' ? $release->sql_file_path : $release->main_file_path;
        $disk = Storage::disk(UpdateService::disk());

        abort_unless($activation->is_active && $activation->license->product_id === $release->product_id, 403);
        abort_unless($path && $disk->exists($path), 404);

        $this->logger->log($request, 'download_file', 'success', "Downloaded {$type} file of v{$release->version}", $activation->license);

        $name = $release->product->slug.'-'.$release->version.($type === 'sql' ? '.sql' : '.zip');

        return $disk->download($path, $name);
    }
}
