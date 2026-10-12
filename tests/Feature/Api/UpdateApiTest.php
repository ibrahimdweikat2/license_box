<?php

use App\Models\Activation;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');

    $this->product = Product::factory()->create();
    $this->license = License::factory()->for($this->product)->create(['updates_expire_at' => now()->addYear()]);
    $this->activation = Activation::factory()->for($this->license)->create();
    $this->key = apiKey();

    foreach (['1.0.0', '1.2.0', '1.10.0'] as $version) {
        $release = ProductVersion::factory()->for($this->product)->create(['version' => $version, 'changelog' => "Changes in $version"]);
        $path = UploadedFile::fake()->create('main.zip', 10)->storeAs("updates/{$this->product->id}/{$release->id}", 'main.zip', 'local');
        $release->update(['main_file_path' => $path]);
    }

    // Unpublished releases are never offered.
    ProductVersion::factory()->for($this->product)->create(['version' => '2.0.0', 'is_published' => false]);
});

function updateRequest(string $endpoint, array $extra = []): TestResponse
{
    return api($endpoint, array_merge([
        'product_id' => test()->product->product_uid,
        'license_key' => test()->license->license_key,
        'instance_id' => test()->activation->instance_id,
    ], $extra), test()->key);
}

it('returns the latest published version using semantic ordering', function () {
    api('latest_version', ['product_id' => $this->product->product_uid], $this->key)
        ->assertOk()
        ->assertJsonPath('data.latest_version', '1.10.0');
});

it('reports an available update', function () {
    updateRequest('check_update', ['current_version' => 'v1.2.0'])
        ->assertOk()
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.latest_version', '1.10.0')
        ->assertJsonPath('data.can_download', true)
        ->assertJsonPath('data.changelog', 'Changes in 1.10.0');
});

it('reports no update when already on the latest version', function () {
    updateRequest('check_update', ['current_version' => '1.10.0'])
        ->assertOk()
        ->assertJsonPath('data.update_available', false);
});

it('requires an active activation to check for updates', function () {
    $this->activation->update(['is_active' => false]);

    updateRequest('check_update', ['current_version' => '1.0.0'])->assertStatus(403)->assertJson(['reason' => 'invalid_activation']);
});

it('says when the update period has ended', function () {
    $this->license->update(['updates_expire_at' => now()->subDay()]);

    updateRequest('check_update', ['current_version' => '1.0.0'])
        ->assertOk()
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.can_download', false);

    updateRequest('download_update', ['version' => '1.10.0'])->assertStatus(403)->assertJson(['reason' => 'updates_expired']);
});

it('issues signed download links that serve the file', function () {
    $response = updateRequest('download_update', ['version' => '1.10.0'])->assertOk();

    expect($response->json('data.main_file_sha256'))->toHaveLength(64)
        ->and($response->json('data.sql_file'))->toBeNull();

    $this->get($response->json('data.main_file'))
        ->assertOk()
        ->assertDownload($this->product->slug.'-1.10.0.zip');
});

it('rejects tampered or expired download links', function () {
    $url = updateRequest('download_update', ['version' => '1.10.0'])->json('data.main_file');

    $this->get($url.'x')->assertForbidden();

    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
});

it('stops serving files once the activation is removed', function () {
    $url = updateRequest('download_update', ['version' => '1.10.0'])->json('data.main_file');
    $this->activation->update(['is_active' => false]);

    $this->get($url)->assertForbidden();
});

it('does not offer unpublished or unknown versions', function (string $version) {
    updateRequest('download_update', ['version' => $version])->assertStatus(404)->assertJson(['reason' => 'version_not_found']);
})->with(['2.0.0', '9.9.9']);
