<?php

use App\Models\Activation;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\License;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ResponseSigner;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->license = License::factory()->for($this->product)->create(['max_activations' => 2]);
    $this->key = apiKey();
});

function activate(array $overrides = []): TestResponse
{
    return api('activate_license', array_merge([
        'product_id' => test()->product->product_uid,
        'license_key' => test()->license->license_key,
        'client_name' => 'Jane',
        'domain' => 'https://www.shop.example.com/path',
    ], $overrides), test()->key);
}

it('rejects requests without a valid API key', function () {
    api('check_connection', [], 'lb_wrong')->assertStatus(401)->assertJson(['status' => false, 'reason' => 'invalid_api_key']);
    $this->postJson('/api/v1/check_connection')->assertStatus(401);
});

it('rejects inactive API keys', function () {
    ApiKey::query()->update(['active' => false]);

    api('check_connection', [], $this->key)->assertStatus(401);
});

it('checks the connection with a signed response', function () {
    $response = api('check_connection', ['nonce' => 'abc123'], $this->key)
        ->assertOk()
        ->assertJson(['status' => true, 'nonce' => 'abc123']);

    expect($response)->toBeSignedByServer();
});

it('detects a tampered response body', function () {
    $response = api('check_connection', [], $this->key);
    $tampered = str_replace('"status":true', '"status":false', $response->getContent());

    expect(app(ResponseSigner::class)->verify($tampered, $response->headers->get('X-LicenseBox-Signature')))->toBeFalse();
});

it('activates a license and returns a verifiable local key', function () {
    $response = activate()->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.domain', 'shop.example.com');
    expect($response)->toBeSignedByServer();

    $activation = Activation::sole();
    expect($activation->domain)->toBe('shop.example.com')
        ->and($response->json('data.instance_id'))->toBe($activation->instance_id);

    $localKey = app(ResponseSigner::class)->decodeToken($response->json('data.local_key'));
    expect($localKey['instance_id'])->toBe($activation->instance_id)
        ->and($localKey['license_hash'])->toBe(hash('sha256', $this->license->license_key));
});

it('accepts lowercase license keys', function () {
    activate(['license_key' => strtolower($this->license->license_key)])->assertOk();
});

it('reuses the activation slot when the same domain activates again', function () {
    $first = activate()->json('data.instance_id');
    $second = activate(['domain' => 'shop.example.com'])->json('data.instance_id');

    expect($second)->toBe($first)->and(Activation::count())->toBe(1);
});

it('enforces the activation limit', function () {
    activate(['domain' => 'a.test'])->assertOk();
    activate(['domain' => 'b.test'])->assertOk();
    activate(['domain' => 'c.test'])->assertStatus(403)->assertJson(['reason' => 'activation_limit']);
});

it('allows unlimited activations when max_activations is zero', function () {
    $this->license->update(['max_activations' => 0]);

    foreach (range(1, 5) as $i) {
        activate(['domain' => "site$i.test"])->assertOk();
    }
});

it('rejects an unknown license key', function () {
    activate(['license_key' => 'NOPE-NOPE-NOPE-NOPE'])->assertStatus(404)->assertJson(['reason' => 'invalid_license']);
});

it('rejects a license key of a different product', function () {
    $other = License::factory()->create();

    activate(['license_key' => $other->license_key])->assertStatus(404);
});

it('rejects an unknown or inactive product', function () {
    activate(['product_id' => 'UNKNOWN1'])->assertStatus(404)->assertJson(['reason' => 'invalid_product']);

    $this->product->update(['status' => 'inactive']);
    activate()->assertStatus(403)->assertJson(['reason' => 'product_inactive']);
});

it('rejects expired licenses and marks them expired', function () {
    $this->license->update(['expires_at' => now()->subMinute()]);

    activate()->assertStatus(403)->assertJson(['reason' => 'license_expired']);
    expect($this->license->fresh()->status)->toBe('expired');
});

it('rejects suspended and revoked licenses', function (string $status, string $reason) {
    $this->license->update(['status' => $status]);

    activate()->assertStatus(403)->assertJson(['reason' => $reason]);
})->with([
    ['suspended', 'license_suspended'],
    ['revoked', 'license_revoked'],
]);

it('enforces allowed domains with wildcards', function () {
    $this->license->update(['allowed_domains' => ['*.example.com'], 'max_activations' => 0]);

    activate(['domain' => 'shop.example.com'])->assertOk();
    activate(['domain' => 'example.com'])->assertOk();
    activate(['domain' => 'evil.test'])->assertStatus(403)->assertJson(['reason' => 'domain_not_allowed']);
    activate(['domain' => 'example.com.evil.test'])->assertStatus(403);
});

it('enforces allowed IPs', function () {
    $this->license->update(['allowed_ips' => ['10.0.0.*']]);

    activate()->assertStatus(403)->assertJson(['reason' => 'ip_not_allowed']);

    $this->license->update(['allowed_ips' => ['127.0.0.1']]);
    activate()->assertOk();
});

it('restricts product-scoped API keys to their product', function () {
    $this->key = apiKey('external', Product::factory()->create());

    activate()->assertStatus(403)->assertJson(['reason' => 'forbidden']);
});

it('validates input with a signed error response', function () {
    $response = api('activate_license', ['product_id' => $this->product->product_uid], $this->key)
        ->assertStatus(422)
        ->assertJson(['status' => false, 'reason' => 'validation_failed']);

    expect($response)->toBeSignedByServer();
});

it('verifies an activated license', function () {
    $instance = activate()->json('data.instance_id');

    api('verify_license', [
        'product_id' => $this->product->product_uid,
        'license_key' => $this->license->license_key,
        'instance_id' => $instance,
        'domain' => 'shop.example.com',
    ], $this->key)->assertOk()->assertJsonPath('data.instance_id', $instance);
});

it('fails verification for another domain or unknown instance', function () {
    $instance = activate()->json('data.instance_id');
    $data = ['product_id' => $this->product->product_uid, 'license_key' => $this->license->license_key];

    api('verify_license', $data + ['instance_id' => $instance, 'domain' => 'other.test'], $this->key)
        ->assertStatus(403)->assertJson(['reason' => 'domain_mismatch']);

    api('verify_license', $data + ['instance_id' => 'missing'], $this->key)
        ->assertStatus(403)->assertJson(['reason' => 'invalid_activation']);
});

it('fails verification after the license is suspended', function () {
    $instance = activate()->json('data.instance_id');
    $this->license->update(['status' => 'suspended']);

    api('verify_license', [
        'product_id' => $this->product->product_uid,
        'license_key' => $this->license->license_key,
        'instance_id' => $instance,
    ], $this->key)->assertStatus(403)->assertJson(['reason' => 'license_suspended']);
});

it('deactivates an installation and frees the slot', function () {
    $this->license->update(['max_activations' => 1]);
    $instance = activate(['domain' => 'a.test'])->json('data.instance_id');

    api('deactivate_license', [
        'product_id' => $this->product->product_uid,
        'license_key' => $this->license->license_key,
        'instance_id' => $instance,
    ], $this->key)->assertOk()->assertJsonPath('data.activations_used', 0);

    activate(['domain' => 'b.test'])->assertOk();
});

it('logs every call without storing the full license key', function () {
    activate();
    activate(['license_key' => 'NOPE-NOPE-NOPE-NOPE']);

    expect(ActivityLog::where('result', 'success')->count())->toBe(1)
        ->and(ActivityLog::where('result', 'failed')->count())->toBe(1);

    $log = ActivityLog::where('result', 'success')->first();
    expect($log->license_id)->toBe($this->license->id)
        ->and($log->payload['license_key'])->not->toBe($this->license->license_key)
        ->and($log->payload['license_key'])->toContain('****');
});

it('rate limits API calls per IP', function () {
    Setting::set('api_rate_limit', 3);

    foreach (range(1, 3) as $i) {
        api('check_connection', [], $this->key)->assertOk();
    }

    api('check_connection', [], $this->key)->assertStatus(429);
});

it('publishes the public key that verifies API responses', function () {
    $signer = app(ResponseSigner::class);

    $response = $this->get('/public-key.pem')
        ->assertOk()
        ->assertHeader('X-LicenseBox-Key-Id', $signer->fingerprint());

    expect($response->getContent())->toBe($signer->publicKey())
        ->toStartWith('-----BEGIN PUBLIC KEY-----')
        ->not->toContain('PRIVATE');

    $signed = api('check_connection', [], $this->key);
    expect(openssl_verify($signed->getContent(), base64_decode($signed->headers->get('X-LicenseBox-Signature')), $response->getContent(), OPENSSL_ALGO_SHA256))->toBe(1);
});
