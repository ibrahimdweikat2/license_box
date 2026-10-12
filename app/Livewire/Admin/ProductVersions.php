<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\ProductVersion;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProductVersions extends Component
{
    use WithFileUploads;

    public Product $product;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $version = '';

    public string $changelog = '';

    public ?string $release_date = null;

    public bool $is_published = true;

    /** @var TemporaryUploadedFile|null */
    public $main_file;

    /** @var TemporaryUploadedFile|null */
    public $sql_file;

    public function create(): void
    {
        $this->resetForm();
        $this->release_date = now()->toDateString();
        $this->showForm = true;
    }

    public function edit(ProductVersion $release): void
    {
        abort_unless($release->product_id === $this->product->id, 404);

        $this->resetForm();
        $this->editingId = $release->id;
        $this->version = $release->version;
        $this->changelog = (string) $release->changelog;
        $this->release_date = $release->release_date?->toDateString();
        $this->is_published = $release->is_published;
        $this->showForm = true;
    }

    public function save(UpdateService $updates): void
    {
        $this->version = UpdateService::cleanVersion($this->version);

        $this->validate([
            'version' => ['required', 'regex:/^\d+(\.\d+){0,3}([-+][0-9A-Za-z.-]+)?$/', 'max:32',
                Rule::unique('product_versions')->where('product_id', $this->product->id)->ignore($this->editingId)],
            'changelog' => ['nullable', 'string', 'max:20000'],
            'release_date' => ['nullable', 'date'],
            'is_published' => ['boolean'],
            'main_file' => [$this->editingId ? 'nullable' : 'required', 'file', 'extensions:zip', 'mimes:zip', 'max:512000'],
            'sql_file' => ['nullable', 'file', 'extensions:sql', 'max:51200'],
        ], ['version.regex' => 'Use a version like 1.2.0.']);

        $attributes = [
            'version' => $this->version,
            'changelog' => $this->changelog ?: null,
            'release_date' => $this->release_date ?: null,
            'is_published' => $this->is_published,
        ];

        $release = $this->editingId
            ? tap($this->product->versions()->findOrFail($this->editingId))->update($attributes)
            : $this->product->versions()->create($attributes);

        if ($this->main_file) {
            $release->main_file_path = $updates->storeFile($release, $this->main_file, 'main');
        }
        if ($this->sql_file) {
            $release->sql_file_path = $updates->storeFile($release, $this->sql_file, 'sql');
        }
        $release->save();

        $this->dispatch('notify', message: "Release {$release->version} saved.");
        $this->closeForm();
    }

    public function togglePublished(ProductVersion $release): void
    {
        abort_unless($release->product_id === $this->product->id, 404);

        $release->update(['is_published' => ! $release->is_published]);
    }

    public function removeSql(ProductVersion $release): void
    {
        abort_unless($release->product_id === $this->product->id, 404);

        if ($release->sql_file_path) {
            Storage::disk(UpdateService::disk())->delete($release->sql_file_path);
            $release->update(['sql_file_path' => null]);
        }
    }

    public function delete(ProductVersion $release, UpdateService $updates): void
    {
        abort_unless($release->product_id === $this->product->id, 404);

        $updates->deleteFiles($release);
        $release->delete();

        $this->dispatch('notify', message: "Release {$release->version} deleted.");
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'version', 'changelog', 'release_date', 'is_published', 'main_file', 'sql_file']);
        $this->resetValidation();
    }

    public function render()
    {
        $releases = $this->product->versions()->get()
            ->sort(fn ($a, $b) => version_compare($b->version, $a->version))
            ->values();

        return view('livewire.admin.product-versions', ['releases' => $releases])
            ->title("{$this->product->name} releases");
    }
}
