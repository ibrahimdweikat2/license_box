<div>
    <x-admin.page-header title="API Keys" subtitle="Sent in the X-API-KEY header. Keys are stored hashed, so the full key is shown only once.">
        <button type="button" wire:click="create" class="btn-primary">New API key</button>
    </x-admin.page-header>

    <div class="grid gap-4 mb-6 sm:grid-cols-2 text-sm">
        <div class="card p-4"><x-admin.badge value="external" /> <span class="ml-1 text-gray-600">Ships inside your product (SDK). Can activate, verify, deactivate and fetch updates. Optionally limited to one product.</span></div>
        <div class="card p-4"><x-admin.badge value="internal" /> <span class="ml-1 text-gray-600">For your own servers (shop, billing). Can also create, read, block and unblock licenses. Never ship it.</span></div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Key</th><th>Type</th><th>Product</th><th>Last used</th><th>State</th><th class="text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($keys as $key)
                        <tr wire:key="key-{{ $key->id }}">
                            <td class="font-medium">{{ $key->name }}</td>
                            <td class="font-mono text-xs text-gray-500">{{ $key->key_prefix }}…</td>
                            <td><x-admin.badge :value="$key->type" /></td>
                            <td class="text-gray-600">{{ $key->product?->name ?? 'All products' }}</td>
                            <td class="text-gray-500 whitespace-nowrap">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><x-admin.badge :value="$key->active ? 'active' : 'inactive'" /></td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <button type="button" wire:click="toggle({{ $key->id }})" class="btn-secondary btn-sm">{{ $key->active ? 'Disable' : 'Enable' }}</button>
                                <button type="button" wire:click="delete({{ $key->id }})" wire:confirm="Delete {{ $key->name }}? Installations using it will stop working." class="btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="7">No API keys yet. Create an external key to use in your product.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showForm)
        <x-admin.dialog title="New API key" max-width="max-w-lg">
            @if ($plainKey)
                <p class="text-sm text-gray-600">Copy this key now. It will not be shown again.</p>
                <div x-data="{ copied: false }" class="mt-3 flex gap-2">
                    <input type="text" readonly value="{{ $plainKey }}" class="input font-mono text-xs" @focus="$el.select()">
                    <button type="button" class="btn-secondary" @click="navigator.clipboard.writeText(@js($plainKey)); copied = true">
                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                    </button>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" wire:click="closeForm" class="btn-primary">Done</button>
                </div>
            @else
                <form wire:submit="save" class="space-y-4">
                    <x-admin.field label="Name" name="name" hint="Where the key is used, e.g. “Demo App SDK”.">
                        <input id="name" type="text" wire:model="name" class="input" required autofocus>
                    </x-admin.field>
                    <x-admin.field label="Type" name="type">
                        <select id="type" wire:model.live="type" class="input">
                            <option value="external">External (for your product)</option>
                            <option value="internal">Internal (for your servers)</option>
                        </select>
                    </x-admin.field>
                    @if ($type === 'external')
                        <x-admin.field label="Limit to product" name="product_id">
                            <select id="product_id" wire:model="product_id" class="input">
                                <option value="">All products</option>
                                @foreach ($products as $id => $productName)
                                    <option value="{{ $id }}">{{ $productName }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>
                    @endif
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeForm" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Create key</button>
                    </div>
                </form>
            @endif
        </x-admin.dialog>
    @endif
</div>
