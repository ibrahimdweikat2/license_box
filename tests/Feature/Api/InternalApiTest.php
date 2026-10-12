<?php

use App\Models\Client;
use App\Models\License;
use App\Models\Product;

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->internal = apiKey('internal');
});

it('refuses external keys on internal endpoints', function () {
    api('create_license', ['product_id' => $this->product->product_uid], apiKey('external'))
        ->assertStatus(403)
        ->assertJson(['reason' => 'forbidden']);
});

it('creates a license with a generated key and a client', function () {
    $response = api('create_license', [
        'product_id' => $this->product->product_uid,
        'type' => 'subscription',
        'max_activations' => 3,
        'expires_at' => now()->addYear()->toDateString(),
        'allowed_domains' => ['*.acme.test'],
        'client_name' => 'Acme',
        'client_email' => 'ops@acme.test',
    ], $this->internal)->assertOk();

    $license = License::sole();
    expect($response->json('data.license_key'))->toBe($license->license_key)
        ->and($license->license_key)->toMatch('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/')
        ->and($license->max_activations)->toBe(3)
        ->and($license->allowed_domains)->toBe(['*.acme.test'])
        ->and($license->client->email)->toBe('ops@acme.test');

    // The same e-mail reuses the existing client.
    api('create_license', ['product_id' => $this->product->product_uid, 'client_email' => 'ops@acme.test'], $this->internal)->assertOk();
    expect(Client::count())->toBe(1);
});

it('rejects duplicate custom license keys', function () {
    License::factory()->for($this->product)->create(['license_key' => 'SAME-SAME-SAME-SAME']);

    api('create_license', ['product_id' => $this->product->product_uid, 'license_key' => 'SAME-SAME-SAME-SAME'], $this->internal)
        ->assertStatus(422);
});

it('gets, blocks and unblocks a license', function () {
    $license = License::factory()->for($this->product)->create();
    $data = ['product_id' => $this->product->product_uid, 'license_key' => $license->license_key];

    api('get_license', $data, $this->internal)->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.activations', []);

    api('block_license', $data, $this->internal)->assertOk()->assertJsonPath('data.status', 'suspended');
    expect($license->fresh()->status)->toBe('suspended');

    api('unblock_license', $data, $this->internal)->assertOk()->assertJsonPath('data.status', 'active');
});
