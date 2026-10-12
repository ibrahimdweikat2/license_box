<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Services\UpdateService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Products')]
class Products extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $product_uid = '';

    public string $description = '';

    public string $status = 'active';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->product_uid = strtoupper(Str::random(8));
        $this->showForm = true;
    }

    public function edit(Product $product): void
    {
        $this->resetForm();
        $this->editingId = $product->id;
        $this->fill($product->only(['name', 'product_uid', 'status']));
        $this->description = (string) $product->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'product_uid' => ['required', 'alpha_num', 'max:32', Rule::unique('products')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['product_uid'] = strtoupper($data['product_uid']);

        if ($this->editingId) {
            Product::findOrFail($this->editingId)->update($data);
        } else {
            $slug = Str::slug($data['name']) ?: 'product';
            $data['slug'] = Product::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
            Product::create($data);
        }

        $this->dispatch('notify', message: $this->editingId ? 'Product updated.' : 'Product created.');
        $this->closeForm();
    }

    public function delete(Product $product, UpdateService $updates): void
    {
        $product->versions->each(fn ($release) => $updates->deleteFiles($release));
        $product->delete();

        $this->dispatch('notify', message: "Deleted {$product->name} and all of its licenses.");
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'product_uid', 'description', 'status']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.products', [
            'products' => Product::withCount(['licenses', 'versions'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('product_uid', 'like', "%{$this->search}%")))
                ->latest()
                ->paginate(15),
        ]);
    }
}
