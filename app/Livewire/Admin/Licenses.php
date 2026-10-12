<?php

namespace App\Livewire\Admin;

use App\Models\License;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Licenses')]
class Licenses extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $type = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'product', 'status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(License $license, string $status): void
    {
        abort_unless(in_array($status, License::STATUSES, true), 422);

        $license->update(['status' => $status]);
        $this->dispatch('notify', message: "License {$license->license_key} is now {$status}.");
    }

    /**
     * Shared by the page and the CSV export so both apply the same filters.
     */
    public static function filteredQuery(array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');

        return License::query()
            ->with(['product', 'client'])
            ->withCount(['activations as active_activations_count' => fn ($q) => $q->where('is_active', true)])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('license_key', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($filters['product'] ?? '', fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['status'] ?? '', fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? '', fn ($q, $type) => $q->where('type', $type))
            ->latest('id');
    }

    public function render()
    {
        $filters = $this->only(['search', 'product', 'status', 'type']);

        return view('livewire.admin.licenses', [
            'licenses' => self::filteredQuery($filters)->paginate(20),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
            'exportUrl' => route('admin.licenses.export', array_filter($filters)),
        ]);
    }
}
