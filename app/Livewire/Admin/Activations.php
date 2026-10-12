<?php

namespace App\Livewire\Admin;

use App\Models\Activation;
use App\Models\Product;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Activations')]
class Activations extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: 'active')]
    public string $state = 'active';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function deactivate(Activation $activation): void
    {
        $activation->update(['is_active' => false]);
        $this->dispatch('notify', message: 'Installation deactivated.');
    }

    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.activations', [
            'activations' => Activation::with('license.product')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('domain', 'like', "%$search%")
                    ->orWhere('ip', 'like', "%$search%")
                    ->orWhere('client_name', 'like', "%$search%")
                    ->orWhereHas('license', fn ($q) => $q->where('license_key', 'like', "%$search%"))))
                ->when($this->product, fn ($q, $id) => $q->whereHas('license', fn ($q) => $q->where('product_id', $id)))
                ->when($this->state !== 'all', fn ($q) => $q->where('is_active', $this->state === 'active'))
                ->latest('activated_at')
                ->paginate(25),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
