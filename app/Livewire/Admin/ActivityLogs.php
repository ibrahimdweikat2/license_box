<?php

namespace App\Livewire\Admin;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Activity Logs')]
class ActivityLogs extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $result = '';

    #[Url(except: '')]
    public string $product = '';

    #[Url(except: '')]
    public string $license = '';

    public ?int $viewing = null;

    public function updated(string $property): void
    {
        if ($property !== 'viewing') {
            $this->resetPage();
        }
    }

    public function show(int $id): void
    {
        $this->viewing = $id;
    }

    public function closeForm(): void
    {
        $this->viewing = null;
    }

    public function prune(): void
    {
        $days = (int) Setting::get('log_retention_days');
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->dispatch('notify', message: "Deleted $deleted log entries older than $days days.");
    }

    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.activity-logs', [
            'logs' => ActivityLog::with(['license', 'product'])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('ip', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")))
                ->when($this->action, fn ($q, $action) => $q->where('action', $action))
                ->when($this->result, fn ($q, $result) => $q->where('result', $result))
                ->when($this->product, fn ($q, $id) => $q->where('product_id', $id))
                ->when($this->license, fn ($q, $id) => $q->where('license_id', $id))
                ->latest('id')
                ->paginate(30),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'products' => Product::orderBy('name')->pluck('name', 'id'),
            'detail' => $this->viewing ? ActivityLog::with(['license', 'product'])->find($this->viewing) : null,
        ]);
    }
}
