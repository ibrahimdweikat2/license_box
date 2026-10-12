<?php

namespace Database\Seeders;

use App\Models\Activation;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Services\KeyGenerator;
use App\Services\UpdateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo admin, product, releases, clients, licenses and API keys.
     */
    public function run(KeyGenerator $keys): void
    {
        // Demo data includes a known admin password; production uses `php artisan licensebox:install`.
        if (app()->isProduction()) {
            $this->command?->error('Demo data is not seeded in production. Run `php artisan licensebox:install` instead.');

            return;
        }

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $product = Product::create([
            'name' => 'Demo App',
            'product_uid' => 'DEMOAPP1',
            'description' => 'Sample product used to try the API and the client SDK.',
        ]);

        foreach (['1.0.0' => '- Initial release', '1.1.0' => "- Added dark mode\n- Fixed login bug"] as $version => $changelog) {
            $release = $product->versions()->create([
                'version' => $version,
                'changelog' => $changelog,
                'release_date' => now()->subDays($version === '1.0.0' ? 30 : 2),
                'is_published' => true,
            ]);
            $release->update(['main_file_path' => $this->demoZip($release->product_id, $release->id, $version)]);
        }

        $clients = Client::factory(5)->create();

        License::create([
            'product_id' => $product->id,
            'client_id' => $clients[0]->id,
            'license_key' => 'DEMO-DEMO-DEMO-DEMO',
            'type' => 'lifetime',
            'max_activations' => 2,
            'updates_expire_at' => now()->addYear(),
        ]);

        foreach ($clients as $i => $client) {
            $license = License::create([
                'product_id' => $product->id,
                'client_id' => $client->id,
                'license_key' => $keys->unique(),
                'type' => $i % 2 ? 'subscription' : 'lifetime',
                'max_activations' => 1 + $i,
                'expires_at' => $i % 2 ? now()->addMonths(6) : null,
                'status' => $i === 4 ? 'suspended' : 'active',
            ]);

            Activation::factory(min($i, $license->max_activations))->create([
                'license_id' => $license->id,
                'activated_at' => now()->subDays(rand(0, 29)),
            ]);
        }

        [, $external] = ApiKey::generate('Demo external key', 'external', $product->id);
        [, $internal] = ApiKey::generate('Demo internal key', 'internal');

        $this->command?->info('Admin login: admin@example.com / password');
        $this->command?->info("Product ID: {$product->product_uid}   Demo license: DEMO-DEMO-DEMO-DEMO");
        $this->command?->info("External API key: $external");
        $this->command?->info("Internal API key: $internal");
    }

    private function demoZip(int $productId, int $releaseId, string $version): string
    {
        $path = "updates/$productId/$releaseId/main.zip";
        $disk = Storage::disk(UpdateService::disk());
        $disk->makeDirectory(dirname($path));

        $zip = new ZipArchive;
        $zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('VERSION', $version);
        $zip->addFromString('README.txt', "Demo App v$version");
        $zip->close();

        return $path;
    }
}
