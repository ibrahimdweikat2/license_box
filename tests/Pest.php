<?php

use App\Models\ApiKey;
use App\Models\Product;
use App\Services\ResponseSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an API key and return its plaintext value.
 */
function apiKey(string $type = 'external', ?Product $product = null): string
{
    return ApiKey::generate('Test key', $type, $product?->id)[1];
}

/**
 * POST to a v1 endpoint with an API key.
 */
function api(string $endpoint, array $data = [], ?string $key = null): TestResponse
{
    return test()->postJson("/api/v1/$endpoint", $data, ['X-API-KEY' => $key ?? apiKey()]);
}

expect()->extend('toBeSignedByServer', function () {
    /** @var TestResponse $response */
    $response = $this->value;
    $signature = (string) $response->headers->get('X-LicenseBox-Signature');

    expect(app(ResponseSigner::class)->verify($response->getContent(), $signature))->toBeTrue();

    return $this;
});
