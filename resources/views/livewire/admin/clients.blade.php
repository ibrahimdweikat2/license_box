<div>
    <x-admin.page-header title="Clients" subtitle="The customers your licenses belong to.">
        <button type="button" wire:click="create" class="btn-primary">New client</button>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <div class="p-4 border-b">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, e-mail or company…" class="input sm:max-w-xs">
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>E-mail</th><th>Company</th><th>Licenses</th><th class="text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}">
                            <td class="font-medium">{{ $client->name }}</td>
                            <td class="text-gray-600">{{ $client->email ?? '—' }}</td>
                            <td class="text-gray-600">{{ $client->company ?? '—' }}</td>
                            <td><a href="{{ route('admin.licenses', ['search' => $client->email ?? $client->name]) }}" wire:navigate class="link">{{ $client->licenses_count }}</a></td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <a href="{{ route('admin.licenses.create', ['client' => $client->id]) }}" wire:navigate class="btn-secondary btn-sm">New license</a>
                                <button type="button" wire:click="edit({{ $client->id }})" class="btn-secondary btn-sm">Edit</button>
                                <button type="button" wire:click="delete({{ $client->id }})" wire:confirm="Delete {{ $client->name }}? Their licenses are kept." class="btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="5">No clients yet.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clients->hasPages())
            <div class="p-4 border-t">{{ $clients->links() }}</div>
        @endif
    </div>

    @if ($showForm)
        <x-admin.dialog :title="$editingId ? 'Edit client' : 'New client'">
            <form wire:submit="save" class="space-y-4">
                <x-admin.field label="Name" name="name">
                    <input id="name" type="text" wire:model="name" class="input" required autofocus>
                </x-admin.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="E-mail" name="email">
                        <input id="email" type="email" wire:model="email" class="input">
                    </x-admin.field>
                    <x-admin.field label="Company" name="company">
                        <input id="company" type="text" wire:model="company" class="input">
                    </x-admin.field>
                </div>
                <x-admin.field label="Notes" name="notes">
                    <textarea id="notes" wire:model="notes" rows="3" class="input"></textarea>
                </x-admin.field>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeForm" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-admin.dialog>
    @endif
</div>
