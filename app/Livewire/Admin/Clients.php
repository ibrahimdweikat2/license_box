<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Clients extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $company = '';

    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(Client $client): void
    {
        $this->resetForm();
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->email = (string) $client->email;
        $this->company = (string) $client->company;
        $this->notes = (string) $client->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $this->editingId
            ? Client::findOrFail($this->editingId)->update($data)
            : Client::create($data);

        $this->dispatch('notify', message: $this->editingId ? 'Client updated.' : 'Client created.');
        $this->closeForm();
    }

    public function delete(Client $client): void
    {
        $client->delete();
        $this->dispatch('notify', message: 'Client deleted. Their licenses were kept without a client.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'company', 'notes']);
        $this->resetValidation();
    }

    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.clients', [
            'clients' => Client::withCount('licenses')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('company', 'like', "%$search%")))
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
}
