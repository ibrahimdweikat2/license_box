<div>
    <x-admin.page-header title="Activations" subtitle="Every installation that has activated a license." />

    <div class="card overflow-hidden">
        <div class="p-4 border-b grid gap-3 sm:grid-cols-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Domain, IP, client or key…" class="input">
            <select wire:model.live="product" class="input">
                <option value="">All products</option>
                @foreach ($products as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="state" class="input">
                <option value="active">Active only</option>
                <option value="inactive">Deactivated only</option>
                <option value="all">All</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Domain / IP</th><th>License</th><th>Product</th><th>Client name</th><th>Activated</th><th>Last check</th><th>State</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($activations as $activation)
                        <tr wire:key="act-{{ $activation->id }}">
                            <td>
                                <div>{{ $activation->domain ?? '—' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $activation->ip }}</div>
                            </td>
                            <td><a href="{{ route('admin.licenses.show', $activation->license_id) }}" wire:navigate class="font-mono text-xs link whitespace-nowrap">{{ $activation->license->license_key }}</a></td>
                            <td class="whitespace-nowrap">{{ $activation->license->product->name }}</td>
                            <td class="text-gray-600">{{ $activation->client_name ?? '—' }}</td>
                            <td class="text-gray-500 whitespace-nowrap">{{ $activation->activated_at?->format('M j, Y') }}</td>
                            <td class="text-gray-500 whitespace-nowrap">{{ $activation->last_checked_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><x-admin.badge :value="$activation->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-right">
                                @if ($activation->is_active)
                                    <button type="button" wire:click="deactivate({{ $activation->id }})" wire:confirm="Deactivate this installation?" class="btn-secondary btn-sm">Deactivate</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="8">No activations found.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($activations->hasPages())
            <div class="p-4 border-t">{{ $activations->links() }}</div>
        @endif
    </div>
</div>
