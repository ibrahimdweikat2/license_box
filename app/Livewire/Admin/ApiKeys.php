<?php

namespace App\Livewire\Admin;

use App\Models\ApiKey;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('API Keys')]
class ApiKeys extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $type = 'external';

    public string $product_id = '';

    /** Plaintext of a just-created key. Shown once, never stored. */
    public ?string $plainKey = null;

    public function create(): void
    {
        $this->reset(['name', 'type', 'product_id', 'plainKey']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in(['external', 'internal'])],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        [, $this->plainKey] = ApiKey::generate(
            $this->name,
            $this->type,
            $this->type === 'external' && $this->product_id !== '' ? (int) $this->product_id : null,
        );
    }

    public function toggle(ApiKey $key): void
    {
        $key->update(['active' => ! $key->active]);
    }

    public function delete(ApiKey $key): void
    {
        $key->delete();
        $this->dispatch('notify', message: 'API key deleted. Requests using it are now rejected.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->reset(['name', 'type', 'product_id', 'plainKey']);
    }

    public function render()
    {
        return view('livewire.admin.api-keys', [
            'keys' => ApiKey::with('product')->latest()->get(),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
