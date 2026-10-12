<?php

namespace App\Livewire\Admin;

use App\Models\ApiKey;
use App\Models\Product;
use App\Services\SdkBuilder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Client SDK')]
class Sdk extends Component
{
    public string $product_id = '';

    public string $key_mode = 'new';

    public string $api_key = '';

    public string $server_url = '';

    public function mount(): void
    {
        $this->product_id = (string) Product::orderBy('name')->value('id');
        $this->server_url = rtrim(config('app.url'), '/');
    }

    public function download(SdkBuilder $builder)
    {
        $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'key_mode' => ['required', 'in:new,existing'],
            'api_key' => ['required_if:key_mode,existing', 'nullable', 'string'],
            'server_url' => ['required', 'url'],
        ]);

        $product = Product::findOrFail($this->product_id);

        if ($this->key_mode === 'existing') {
            $key = ApiKey::findByPlain(trim($this->api_key));
            if (! $key || ($key->product_id !== null && $key->product_id !== $product->id)) {
                $this->addError('api_key', 'This key does not exist, is disabled, or belongs to another product.');

                return null;
            }
            if ($key->type === 'internal') {
                $this->addError('api_key', 'Never ship an internal key inside your product. Use an external key.');

                return null;
            }
            $plain = trim($this->api_key);
        } else {
            [, $plain] = ApiKey::generate("{$product->name} SDK (".now()->format('Y-m-d').')', 'external', $product->id);
        }

        $source = $builder->build($product, $plain, $this->server_url);

        $this->dispatch('notify', message: 'SDK generated.');

        return response()->streamDownload(function () use ($source) {
            echo $source;
        }, 'LicenseBoxClient.php', [
            'Content-Type' => 'application/x-httpd-php',
        ]);
    }

    public function render()
    {
        $product = Product::find($this->product_id);

        return view('livewire.admin.sdk', [
            'products' => Product::orderBy('name')->pluck('name', 'id'),
            'productUid' => $product?->product_uid ?? 'YOUR_PRODUCT_ID',
        ]);
    }
}
