<?php

use App\Livewire\Admin;
use App\Models\Activation;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Setting;
use App\Models\User;
use App\Services\SdkBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->product = Product::factory()->create(['name' => 'Acme CRM']);
    $this->license = License::factory()->for($this->product)->create();
    Activation::factory()->for($this->license)->create();
    ActivityLog::create(['action' => 'activate', 'result' => 'success', 'license_id' => $this->license->id, 'product_id' => $this->product->id]);
});

it('redirects guests to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with(['/dashboard', '/admin/products', '/admin/licenses', '/admin/settings', '/admin/licenses/export']);

it('renders every admin page', function () {
    $this->actingAs($this->admin);

    foreach ([
        route('dashboard'),
        route('admin.products'),
        route('admin.products.versions', $this->product),
        route('admin.licenses'),
        route('admin.licenses.create'),
        route('admin.licenses.show', $this->license),
        route('admin.licenses.edit', $this->license),
        route('admin.activations'),
        route('admin.clients'),
        route('admin.api-keys'),
        route('admin.logs'),
        route('admin.sdk'),
        route('admin.settings'),
        route('profile.edit'),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('renders the public pages', function () {
    $this->get('/')->assertOk()->assertSee('Open admin panel');
    $this->get('/docs')->assertOk()->assertSee('activate_license');
});

it('closes registration once an admin exists', function () {
    $this->get('/register')->assertForbidden();
    $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertForbidden();
});

it('creates, edits and deletes products', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\Products::class)
        ->call('create')
        ->set('name', 'Super Plugin')
        ->set('product_uid', 'super01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Super Plugin');

    $product = Product::where('product_uid', 'SUPER01')->sole();
    expect($product->slug)->toBe('super-plugin');

    Livewire::test(Admin\Products::class)
        ->call('edit', $product->id)
        ->set('product_uid', $this->product->product_uid)
        ->call('save')
        ->assertHasErrors(['product_uid' => 'unique']);

    Livewire::test(Admin\Products::class)->call('delete', $product->id);
    expect(Product::find($product->id))->toBeNull();
});

it('uploads, publishes and deletes releases', function () {
    Storage::fake('local');
    $this->actingAs($this->admin);

    Livewire::test(Admin\ProductVersions::class, ['product' => $this->product])
        ->call('create')
        ->set('version', 'v2.0.0')
        ->set('changelog', 'Big update')
        ->set('main_file', UploadedFile::fake()->create('release.zip', 100, 'application/zip'))
        ->set('sql_file', UploadedFile::fake()->createWithContent('update.sql', 'ALTER TABLE x ADD y INT;'))
        ->call('save')
        ->assertHasNoErrors();

    $release = ProductVersion::where('version', '2.0.0')->sole();
    Storage::disk('local')->assertExists($release->main_file_path);
    Storage::disk('local')->assertExists($release->sql_file_path);

    Livewire::test(Admin\ProductVersions::class, ['product' => $this->product])->call('togglePublished', $release->id);
    expect($release->fresh()->is_published)->toBeFalse();

    Livewire::test(Admin\ProductVersions::class, ['product' => $this->product])->call('delete', $release->id);
    Storage::disk('local')->assertMissing($release->main_file_path);
});

it('requires a zip file for new releases', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\ProductVersions::class, ['product' => $this->product])
        ->call('create')
        ->set('version', '3.0.0')
        ->call('save')
        ->assertHasErrors(['main_file' => 'required']);

    Livewire::test(Admin\ProductVersions::class, ['product' => $this->product])
        ->call('create')
        ->set('version', '3.0.0')
        ->set('main_file', UploadedFile::fake()->create('evil.php', 1))
        ->call('save')
        ->assertHasErrors(['main_file']);
});

it('creates a single license with a new client', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\LicenseForm::class)
        ->set('product_id', (string) $this->product->id)
        ->set('new_client_name', 'Jane')
        ->set('new_client_email', 'jane@example.test')
        ->set('max_activations', 3)
        ->set('allowed_domains', "example.com\n*.example.com")
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $license = License::latest('id')->first();
    expect($license->client->email)->toBe('jane@example.test')
        ->and($license->allowed_domains)->toBe(['example.com', '*.example.com'])
        ->and($license->max_activations)->toBe(3);
});

it('bulk generates licenses with a prefix', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\LicenseForm::class)
        ->set('product_id', (string) $this->product->id)
        ->set('quantity', 5)
        ->set('key_prefix', 'pro')
        ->call('save')
        ->assertHasNoErrors();

    expect(License::where('license_key', 'like', 'PRO-%')->count())->toBe(5);
});

it('edits a license', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\LicenseForm::class, ['license' => $this->license])
        ->assertSet('license_key', $this->license->license_key)
        ->set('status', 'suspended')
        ->set('expires_at', '2030-01-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->license->fresh())
        ->status->toBe('suspended')
        ->expires_at->toDateString()->toBe('2030-01-01');
});

it('suspends licenses and deactivates installations from the detail page', function () {
    $this->actingAs($this->admin);
    $activation = $this->license->activations()->first();

    Livewire::test(Admin\LicenseShow::class, ['license' => $this->license])
        ->call('setStatus', 'revoked')
        ->call('deactivate', $activation->id);

    expect($this->license->fresh()->status)->toBe('revoked')
        ->and($activation->fresh()->is_active)->toBeFalse();
});

it('does not touch activations of other licenses', function () {
    $this->actingAs($this->admin);
    $foreign = Activation::factory()->create();

    Livewire::test(Admin\LicenseShow::class, ['license' => $this->license])
        ->call('deactivate', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('filters licenses', function () {
    $this->actingAs($this->admin);
    $suspended = License::factory()->for($this->product)->suspended()->create();

    Livewire::test(Admin\Licenses::class)
        ->set('status', 'suspended')
        ->assertSee($suspended->license_key)
        ->assertDontSee($this->license->license_key);
});

it('exports licenses to CSV', function () {
    $this->actingAs($this->admin);
    $this->license->client()->associate(Client::factory()->create(['name' => '=HYPERLINK("x")']))->save();

    $csv = $this->get(route('admin.licenses.export'))->assertOk()->streamedContent();

    expect($csv)->toContain($this->license->license_key)
        ->and($csv)->toContain("'=HYPERLINK");
});

it('creates API keys showing the plaintext once', function () {
    $this->actingAs($this->admin);

    $component = Livewire::test(Admin\ApiKeys::class)
        ->call('create')
        ->set('name', 'Shop')
        ->set('type', 'internal')
        ->call('save');

    $plain = $component->get('plainKey');
    expect($plain)->toStartWith('lb_')
        ->and(ApiKey::findByPlain($plain)->type)->toBe('internal')
        ->and(ApiKey::where('key_hash', $plain)->exists())->toBeFalse();

    $component->call('closeForm')->assertSet('plainKey', null);
});

it('manages clients', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\Clients::class)
        ->call('create')
        ->set('name', 'Bob')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email'])
        ->set('email', 'bob@example.test')
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::where('email', 'bob@example.test')->exists())->toBeTrue();
});

it('saves settings', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\Settings::class)
        ->set('site_name', 'My Licenses')
        ->set('api_rate_limit', 120)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('site_name'))->toBe('My Licenses')
        ->and((int) Setting::get('api_rate_limit'))->toBe(120);
});

it('prunes old logs', function () {
    $this->actingAs($this->admin);
    ActivityLog::create(['action' => 'verify', 'result' => 'success'])->forceFill(['created_at' => now()->subYear()])->save();

    Livewire::test(Admin\ActivityLogs::class)->call('prune');

    expect(ActivityLog::count())->toBe(1);
});

it('generates a configured SDK with a new product-scoped key', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\Sdk::class)
        ->set('product_id', (string) $this->product->id)
        ->call('download')
        ->assertFileDownloaded('LicenseBoxClient.php');

    $key = ApiKey::latest('id')->first();
    expect($key->type)->toBe('external')->and($key->product_id)->toBe($this->product->id);
});

it('refuses to embed an internal key in the SDK', function () {
    $this->actingAs($this->admin);

    Livewire::test(Admin\Sdk::class)
        ->set('key_mode', 'existing')
        ->set('api_key', apiKey('internal'))
        ->call('download')
        ->assertHasErrors(['api_key']);
});

it('builds an SDK that is valid PHP with the values embedded', function () {
    $source = app(SdkBuilder::class)->build($this->product, 'lb_test', 'https://licenses.example.com');

    expect($source)->toContain("'https://licenses.example.com'")
        ->toContain("'{$this->product->product_uid}'")
        ->toContain('BEGIN PUBLIC KEY')
        ->not->toContain('%%');

    $file = tempnam(sys_get_temp_dir(), 'sdk');
    file_put_contents($file, $source);
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file), $output, $code);
    unlink($file);

    expect($code)->toBe(0);
});
