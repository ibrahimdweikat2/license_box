<div>
    <x-admin.page-header title="Licenses" subtitle="Issue, limit and revoke license keys.">
        <a href="{{ $exportUrl }}" class="btn-secondary">Export CSV</a>
        <a href="{{ route('admin.licenses.create') }}" wire:navigate class="btn-primary">New license</a>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <div class="p-4 border-b grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Key, client name or e-mail…" class="input">
            <select wire:model.live="product" class="input">
                <option value="">All products</option>
                @foreach ($products as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="input">
                <option value="">Any status</option>
                @foreach (\App\Models\License::STATUSES as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select wire:model.live="type" class="input">
                <option value="">Any type</option>
                @foreach (\App\Models\License::TYPES as $t)
                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>License key</th><th>Product</th><th>Client</th><th>Type</th><th>Installs</th><th>Expires</th><th>Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($licenses as $license)
                        <tr wire:key="license-{{ $license->id }}">
                            <td><a href="{{ route('admin.licenses.show', $license) }}" wire:navigate class="font-mono text-xs link whitespace-nowrap">{{ $license->license_key }}</a></td>
                            <td class="whitespace-nowrap">{{ $license->product->name }}</td>
                            <td class="text-gray-600">{{ $license->client?->name ?? '—' }}</td>
                            <td class="text-gray-600">{{ ucfirst($license->type) }}</td>
                            <td class="whitespace-nowrap">{{ $license->active_activations_count }} / {{ $license->max_activations ?: '∞' }}</td>
                            <td class="text-gray-500 whitespace-nowrap">
                                @if ($license->expires_at)
                                    <span class="{{ $license->expires_at->isPast() ? 'text-red-600' : '' }}">{{ $license->expires_at->format('M j, Y') }}</span>
                                @else
                                    Never
                                @endif
                            </td>
                            <td><x-admin.badge :value="$license->status" /></td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <a href="{{ route('admin.licenses.edit', $license) }}" wire:navigate class="btn-secondary btn-sm">Edit</a>
                                @if ($license->status === 'active')
                                    <button type="button" wire:click="setStatus({{ $license->id }}, 'suspended')" class="btn-secondary btn-sm">Suspend</button>
                                @else
                                    <button type="button" wire:click="setStatus({{ $license->id }}, 'active')" class="btn-secondary btn-sm">Activate</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="8">No licenses match your filters.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($licenses->hasPages())
            <div class="p-4 border-t">{{ $licenses->links() }}</div>
        @endif
    </div>
</div>
