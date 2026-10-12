<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\ResponseSigner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Never touch the real keypair in storage/app/private/keys.
    $this->keyPath = storage_path('framework/testing/keys-'.uniqid());
    config(['licensebox.signing.path' => $this->keyPath]);
});

afterEach(function () {
    File::deleteDirectory($this->keyPath);
});

function inProduction(): void
{
    app()->detectEnvironment(fn () => 'production');
}

it('creates the signing keys and the first admin on install', function () {
    $this->artisan('licensebox:install', ['--name' => 'Owner', '--email' => 'owner@example.com', '--password' => 'secret-pass-123'])
        ->assertSuccessful();

    expect(app(ResponseSigner::class)->hasKeys())->toBeTrue();
    $admin = User::sole();
    expect($admin->email)->toBe('owner@example.com')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(password_verify('secret-pass-123', $admin->password))->toBeTrue();
});

it('never replaces existing keys or admins when install runs again', function () {
    $signer = app(ResponseSigner::class);
    $signer->regenerate();
    $publicKey = $signer->publicKey();
    User::factory()->create();

    $this->artisan('licensebox:install', ['--email' => 'second@example.com', '--password' => 'secret-pass-123'])
        ->assertSuccessful();

    expect(File::get($signer->path('public.pem')))->toBe($publicKey)
        ->and(User::count())->toBe(1);
});

it('rejects an invalid admin on install', function () {
    $this->artisan('licensebox:install', ['--name' => 'Owner', '--email' => 'not-an-email', '--password' => 'short'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('needs admin credentials when install runs without a terminal', function () {
    $this->artisan('licensebox:install', ['--no-interaction' => true])->assertFailed();

    expect(User::count())->toBe(0);
});

it('does not create signing keys on the fly in production', function () {
    inProduction();

    expect(fn () => app(ResponseSigner::class)->sign('data'))->toThrow(RuntimeException::class, 'Signing keys not found');
    expect(app(ResponseSigner::class)->hasKeys())->toBeFalse();
});

it('still creates signing keys on the fly outside production', function () {
    app(ResponseSigner::class)->sign('data');

    expect(app(ResponseSigner::class)->hasKeys())->toBeTrue();
});

it('does not seed demo data in production', function () {
    inProduction();

    app(DatabaseSeeder::class)->setContainer(app())();

    expect(User::count())->toBe(0)->and(Product::count())->toBe(0);
});

it('uses the client IP from X-Forwarded-For when proxies are trusted', function () {
    config(['licensebox.trusted_proxies' => '*']);
    (new AppServiceProvider(app()))->boot();

    $this->postJson('/api/v1/activate_license', [], ['X-API-KEY' => apiKey(), 'X-Forwarded-For' => '203.0.113.7']);

    expect(ActivityLog::latest('id')->value('ip'))->toBe('203.0.113.7');
});

it('ignores X-Forwarded-For when no proxy is trusted', function () {
    $this->postJson('/api/v1/activate_license', [], ['X-API-KEY' => apiKey(), 'X-Forwarded-For' => '203.0.113.7']);

    expect(ActivityLog::latest('id')->value('ip'))->toBe('127.0.0.1');
});

it('passes the preflight check on a healthy install', function () {
    app(ResponseSigner::class)->regenerate();
    User::factory()->create();
    Cache::forever('licensebox:scheduler_heartbeat', now()->timestamp);

    $this->artisan('licensebox:check')->assertSuccessful();
});

it('fails the preflight check when signing keys or release files are missing', function () {
    User::factory()->create();
    ProductVersion::factory()->create(['main_file_path' => 'updates/missing/release.zip']);

    $this->artisan('licensebox:check')
        ->expectsOutputToContain('Signing keys exist')
        ->expectsOutputToContain('Release files are present')
        ->assertFailed();
});

it('fails the preflight check when debug is on in production', function () {
    app(ResponseSigner::class)->regenerate();
    inProduction();
    config(['app.debug' => true]);

    $this->artisan('licensebox:check')->expectsOutputToContain('APP_DEBUG is off')->assertFailed();
});
