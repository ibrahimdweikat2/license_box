<div>
    <a href="{{ route('admin.licenses') }}" wire:navigate class="text-sm link">&larr; Licenses</a>
    <x-admin.page-header :title="$license->license_key" :subtitle="$license->product->name">
        <button type="button" x-data="{ copied: false }"
                @click="navigator.clipboard.writeText(@js($license->license_key)); copied = true; setTimeout(() => copied = false, 1500)"
                class="btn-secondary"><span x-text="copied ? 'Copied!' : 'Copy key'"></span></button>
        <a href="{{ route('admin.licenses.edit', $license) }}" wire:navigate class="btn-secondary">Edit</a>
        @if ($license->status === 'active')
            <button type="button" wire:click="setStatus('suspended')" class="btn-secondary">Suspend</button>
            <button type="button" wire:click="setStatus('revoked')" wire:confirm="Revoke this license? Installations will stop working at their next check." class="btn-danger">Revoke</button>
        @else
            <button type="button" wire:click="setStatus('active')" class="btn-primary">Reactivate</button>
        @endif
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6 lg:col-span-1">
            <dl class="space-y-3 text-sm">
                @php
                    $rows = [
                        'Status' => null,
                        'Type' => ucfirst($license->type),
                        'Client' => $license->client ? $license->client->name.($license->client->email ? " <{$license->client->email}>" : '') : '—',
                        'Installations' => $activations->where('is_active', true)->count().' / '.($license->max_activations ?: '∞'),
                        'Expires' => $license->expires_at?->format('M j, Y') ?? 'Never',
                        'Updates until' => $license->updates_expire_at?->format('M j, Y') ?? 'Forever',
                        'Allowed domains' => $license->allowed_domains ? implode(', ', $license->allowed_domains) : 'Any',
                        'Allowed IPs' => $license->allowed_ips ? implode(', ', $license->allowed_ips) : 'Any',
                        'Created' => $license->created_at->format('M j, Y H:i'),
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="text-right break-all">
                            @if ($label === 'Status')
                                <x-admin.badge :value="$license->status" />
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
            @if ($license->notes)
                <div class="mt-4 rounded bg-yellow-50 p-3 text-sm text-yellow-900 whitespace-pre-line">{{ $license->notes }}</div>
            @endif
            <button type="button" wire:click="delete" wire:confirm="Permanently delete this license and its activations?" class="mt-6 text-sm text-red-600 hover:underline">Delete license</button>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b"><h2 class="font-semibold">Installations</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Domain / IP</th><th>Client name</th><th>Activated</th><th>Last check</th><th>State</th><th></th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($activations as $activation)
                                <tr wire:key="activation-{{ $activation->id }}">
                                    <td>
                                        <div>{{ $activation->domain ?? '—' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $activation->ip }}</div>
                                    </td>
                                    <td class="text-gray-600">{{ $activation->client_name ?? '—' }}</td>
                                    <td class="text-gray-500 whitespace-nowrap">{{ $activation->activated_at?->format('M j, Y') }}</td>
                                    <td class="text-gray-500 whitespace-nowrap">{{ $activation->last_checked_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td><x-admin.badge :value="$activation->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($activation->is_active)
                                            <button type="button" wire:click="deactivate({{ $activation->id }})" wire:confirm="Deactivate this installation?" class="btn-secondary btn-sm">Deactivate</button>
                                        @else
                                            <button type="button" wire:click="deleteActivation({{ $activation->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty colspan="6">Not activated anywhere yet.</x-admin.empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="px-6 py-4 border-b flex justify-between">
                    <h2 class="font-semibold">Recent API calls</h2>
                    <a href="{{ route('admin.logs', ['license' => $license->id]) }}" wire:navigate class="text-sm link">All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="font-mono text-xs">{{ $log->action }}</td>
                                    <td><x-admin.badge :value="$log->result" /></td>
                                    <td class="text-gray-600">{{ $log->message }}</td>
                                    <td class="font-mono text-xs text-gray-500">{{ $log->ip }}</td>
                                    <td class="text-xs text-gray-400 whitespace-nowrap">{{ $log->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <x-admin.empty colspan="5">No API calls for this license yet.</x-admin.empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
