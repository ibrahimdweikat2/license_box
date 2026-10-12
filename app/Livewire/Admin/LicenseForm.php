<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\License;
use App\Models\Product;
use App\Services\KeyGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Create (single or bulk) and edit licenses.
 */
class LicenseForm extends Component
{
    public ?License $license = null;

    public string $product_id = '';

    public string $client_id = '';

    public string $new_client_name = '';

    public string $new_client_email = '';

    public string $license_key = '';

    public string $key_prefix = '';

    public int $quantity = 1;

    public string $type = 'lifetime';

    public int $max_activations = 1;

    public ?string $expires_at = null;

    public ?string $updates_expire_at = null;

    public string $allowed_domains = '';

    public string $allowed_ips = '';

    public string $status = 'active';

    public string $notes = '';

    public function mount(?License $license = null): void
    {
        if ($license?->exists) {
            $this->license = $license;
            $this->fill([
                'product_id' => (string) $license->product_id,
                'client_id' => (string) $license->client_id,
                'license_key' => $license->license_key,
                'type' => $license->type,
                'max_activations' => $license->max_activations,
                'expires_at' => $license->expires_at?->format('Y-m-d'),
                'updates_expire_at' => $license->updates_expire_at?->format('Y-m-d'),
                'allowed_domains' => implode("\n", (array) $license->allowed_domains),
                'allowed_ips' => implode("\n", (array) $license->allowed_ips),
                'status' => $license->status,
                'notes' => (string) $license->notes,
            ]);

            return;
        }

        $this->license = null;
        $this->product_id = (string) (request()->integer('product') ?: Product::orderBy('name')->value('id'));
        $this->client_id = (string) request()->integer('client') ?: '';
    }

    protected function rules(): array
    {
        $creating = $this->license === null;

        return [
            'product_id' => ['required', 'exists:products,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client_name' => ['nullable', 'string', 'max:191'],
            'new_client_email' => ['nullable', 'email', 'max:191'],
            'license_key' => [
                $creating ? 'nullable' : 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('licenses', 'license_key')->ignore($this->license?->id),
            ],
            'key_prefix' => ['nullable', 'alpha_num', 'max:10'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'type' => ['required', Rule::in(License::TYPES)],
            'max_activations' => ['required', 'integer', 'min:0', 'max:100000'],
            'expires_at' => ['nullable', 'date'],
            'updates_expire_at' => ['nullable', 'date'],
            'allowed_domains' => ['nullable', 'string', 'max:5000'],
            'allowed_ips' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(License::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function messages(): array
    {
        return ['license_key.regex' => 'Only letters, numbers and dashes are allowed.'];
    }

    public function save(KeyGenerator $keys)
    {
        $this->validate();

        if ($this->license === null && $this->quantity > 1 && $this->license_key !== '') {
            $this->addError('license_key', 'Leave the key empty when generating several licenses.');

            return null;
        }

        $attributes = [
            'product_id' => (int) $this->product_id,
            'client_id' => $this->resolveClientId(),
            'type' => $this->type,
            'max_activations' => $this->max_activations,
            'expires_at' => $this->expires_at ?: null,
            'updates_expire_at' => $this->updates_expire_at ?: null,
            'allowed_domains' => self::lines($this->allowed_domains),
            'allowed_ips' => self::lines($this->allowed_ips),
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        if ($this->license) {
            $this->license->update($attributes + ['license_key' => strtoupper($this->license_key)]);
            session()->flash('notify', 'License updated.');

            return $this->redirectRoute('admin.licenses.show', $this->license, navigate: true);
        }

        $created = DB::transaction(function () use ($attributes, $keys) {
            return collect(range(1, $this->quantity))->map(fn () => License::create($attributes + [
                'license_key' => $this->license_key !== '' ? strtoupper($this->license_key) : $keys->unique($this->key_prefix),
            ]));
        });

        if ($created->count() === 1) {
            session()->flash('notify', 'License created.');

            return $this->redirectRoute('admin.licenses.show', $created->first(), navigate: true);
        }

        session()->flash('notify', "{$created->count()} licenses created.");

        return $this->redirectRoute('admin.licenses', ['product' => $this->product_id], navigate: true);
    }

    public function generateKey(KeyGenerator $keys): void
    {
        $this->license_key = $keys->unique($this->key_prefix);
    }

    private function resolveClientId(): ?int
    {
        if ($this->new_client_name !== '' || $this->new_client_email !== '') {
            $client = $this->new_client_email !== ''
                ? Client::firstOrCreate(['email' => $this->new_client_email], ['name' => $this->new_client_name ?: $this->new_client_email])
                : Client::create(['name' => $this->new_client_name]);

            return $client->id;
        }

        return $this->client_id !== '' ? (int) $this->client_id : null;
    }

    /** One entry per line (commas also accepted) -> array, or null when empty. */
    private static function lines(string $value): ?array
    {
        $items = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value))));

        return $items ?: null;
    }

    public function render()
    {
        return view('livewire.admin.license-form', [
            'products' => Product::orderBy('name')->pluck('name', 'id'),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'email']),
        ])->title($this->license ? 'Edit license' : 'New license');
    }
}
