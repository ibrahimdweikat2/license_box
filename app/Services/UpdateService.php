<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class UpdateService
{
    public function latest(Product $product): ?ProductVersion
    {
        return $product->latestPublishedVersion();
    }

    public function check(Product $product, License $license, string $currentVersion): array
    {
        $latest = $this->latest($product);
        $available = $latest !== null && version_compare($latest->version, self::cleanVersion($currentVersion), '>');

        return [
            'update_available' => $available,
            'current_version' => $currentVersion,
            'latest_version' => $latest?->version,
            'changelog' => $available ? $latest->changelog : null,
            'release_date' => $available ? $latest->release_date?->toDateString() : null,
            'has_sql' => $available && $latest->sql_file_path !== null,
            'can_download' => $available && $license->canReceiveUpdates(),
            'updates_expire_at' => $license->updates_expire_at?->toIso8601String(),
        ];
    }

    /**
     * Return short-lived signed URLs for a published version's files.
     */
    public function downloadLinks(Product $product, License $license, Activation $activation, string $version): array
    {
        if (! $license->canReceiveUpdates()) {
            throw new LicenseException('updates_expired', 'Your update period has ended. Renew your license to download updates.', 403);
        }

        $release = $product->versions()
            ->where('version', self::cleanVersion($version))
            ->where('is_published', true)
            ->first();

        if (! $release || ! $release->main_file_path) {
            throw new LicenseException('version_not_found', 'This version is not available for download.', 404);
        }

        $expires = now()->addMinutes((int) Setting::get('download_link_minutes'));
        $link = fn (string $type) => URL::temporarySignedRoute('api.updates.file', $expires, [
            'release' => $release->id,
            'type' => $type,
            'activation' => $activation->id,
        ]);

        return [
            'version' => $release->version,
            'changelog' => $release->changelog,
            'main_file' => $link('main'),
            'main_file_sha256' => $this->checksum($release->main_file_path),
            'sql_file' => $release->sql_file_path ? $link('sql') : null,
            'sql_file_sha256' => $release->sql_file_path ? $this->checksum($release->sql_file_path) : null,
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    /**
     * Store an uploaded file for a release and return its storage path.
     */
    public function storeFile(ProductVersion $release, UploadedFile $file, string $type): string
    {
        $name = $type === 'sql' ? 'update.sql' : 'main.zip';

        return $file->storeAs("updates/{$release->product_id}/{$release->id}", $name, self::disk());
    }

    public function deleteFiles(ProductVersion $release): void
    {
        Storage::disk(self::disk())->deleteDirectory("updates/{$release->product_id}/{$release->id}");
    }

    public static function disk(): string
    {
        return config('licensebox.updates_disk', 'local');
    }

    /** "v1.2.0" -> "1.2.0" */
    public static function cleanVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    private function checksum(string $path): ?string
    {
        $disk = Storage::disk(self::disk());

        if (! $disk->exists($path)) {
            return null;
        }

        $stream = $disk->readStream($path);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
