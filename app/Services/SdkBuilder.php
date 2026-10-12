<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\File;

/**
 * Produces client-sdk/LicenseBoxClient.php pre-filled with this server's URL, a product,
 * an API key and the response-signing public key.
 */
class SdkBuilder
{
    public function __construct(private ResponseSigner $signer) {}

    public function build(Product $product, string $apiKey, ?string $serverUrl = null): string
    {
        $values = [
            'SERVER_URL' => rtrim($serverUrl ?? config('app.url'), '/'),
            'PRODUCT_ID' => $product->product_uid,
            'API_KEY' => $apiKey,
            'PUBLIC_KEY' => $this->signer->publicKey(),
        ];

        $source = File::get(base_path('client-sdk/LicenseBoxClient.php'));

        foreach ($values as $name => $value) {
            $source = str_replace("'%%{$name}%%'", var_export($value, true), $source);
        }

        return $source;
    }
}
