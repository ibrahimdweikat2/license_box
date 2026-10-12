<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\License;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\LicenseException;
use App\Services\LicenseService;
use App\Services\ResponseSigner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

abstract class ApiController extends Controller
{
    public function __construct(
        protected LicenseService $licenses,
        protected ResponseSigner $signer,
        protected ActivityLogger $logger,
    ) {}

    /**
     * Validate input, run the action, log the outcome and return a signed response.
     * The closure receives the validated data and may set $context['license'|'product']
     * so the log entry is linked; it returns [message, data].
     *
     * @param  Closure(array, array &$context): array{0: string, 1: mixed}  $action
     */
    protected function handle(Request $request, string $logAction, array $rules, Closure $action): Response
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $this->logger->log($request, $logAction, 'failed', $message);

            return $this->respond($request, false, $message, ['errors' => $validator->errors()->toArray()], 422, 'validation_failed');
        }

        $context = ['license' => null, 'product' => null];

        try {
            [$message, $data] = $action($validator->validated(), $context);
        } catch (LicenseException $e) {
            $this->logger->log($request, $logAction, 'failed', $e->getMessage(), $context['license'], $context['product']);

            return $this->respond($request, false, $e->getMessage(), null, $e->status, $e->reason);
        }

        $this->logger->log($request, $logAction, 'success', $message, $context['license'], $context['product']);

        return $this->respond($request, true, $message, $data);
    }

    /**
     * Resolve the product and enforce product-scoped API keys.
     */
    protected function product(Request $request, string $productUid): Product
    {
        $product = $this->licenses->findProduct($productUid);

        /** @var ApiKey|null $key */
        $key = $request->attributes->get('api_key');
        if ($key && $key->product_id !== null && $key->product_id !== $product->id) {
            throw new LicenseException('forbidden', 'This API key is not valid for this product.', 403);
        }

        return $product;
    }

    protected function license(Product $product, string $key, array &$context): License
    {
        $context['product'] = $product;

        return $context['license'] = $this->licenses->findLicense($product, $key);
    }

    protected function respond(Request $request, bool $status, string $message, mixed $data = null, int $code = 200, ?string $reason = null): Response
    {
        return $this->signer->response($request, $status, $message, $data, $code, $reason);
    }

    protected static function baseRules(): array
    {
        return [
            'product_id' => ['required', 'string', 'max:32'],
            'nonce' => ['nullable', 'string', 'max:64'],
        ];
    }
}
