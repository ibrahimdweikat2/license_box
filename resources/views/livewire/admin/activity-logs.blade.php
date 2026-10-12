<div>
    <x-admin.page-header title="Activity Logs" subtitle="Every API call, successful or not. License keys are masked.">
        <button type="button" wire:click="prune" wire:confirm="Delete log entries older than {{ \App\Models\Setting::get('log_retention_days') }} days?" class="btn-secondary">Prune old logs</button>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <div class="p-4 border-b grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="IP or message…" class="input">
            <select wire:model.live="action" class="input">
                <option value="">All actions</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
            <select wire:model.live="result" class="input">
                <option value="">Any result</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </select>
            <select wire:model.live="product" class="input">
                <option value="">All products</option>
                @foreach ($products as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        @if ($license)
            <div class="px-4 py-2 border-b bg-indigo-50 text-sm text-indigo-800 flex justify-between">
                Showing calls for one license.
                <button type="button" wire:click="$set('license', '')" class="link">Clear</button>
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Time</th><th>Action</th><th>Result</th><th>Message</th><th>License</th><th>IP</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="text-gray-500 whitespace-nowrap text-xs">{{ $log->created_at?->format('M j, H:i:s') }}</td>
                            <td class="font-mono text-xs">{{ $log->action }}</td>
                            <td><x-admin.badge :value="$log->result" /></td>
                            <td class="text-gray-600 max-w-xs truncate">{{ $log->message }}</td>
                            <td>
                                @if ($log->license)
                                    <a href="{{ route('admin.licenses.show', $log->license) }}" wire:navigate class="font-mono text-xs link whitespace-nowrap">{{ \App\Services\ActivityLogger::mask($log->license->license_key) }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="font-mono text-xs text-gray-500">{{ $log->ip }}</td>
                            <td class="text-right"><button type="button" wire:click="show({{ $log->id }})" class="text-xs link">Details</button></td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="7">No log entries match your filters.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="p-4 border-t">{{ $logs->links() }}</div>
        @endif
    </div>

    @if ($detail)
        <x-admin.dialog title="Log entry #{{ $detail->id }}">
            <dl class="grid grid-cols-3 gap-2 text-sm">
                <dt class="text-gray-500">Time</dt><dd class="col-span-2">{{ $detail->created_at }}</dd>
                <dt class="text-gray-500">Action</dt><dd class="col-span-2 font-mono">{{ $detail->action }}</dd>
                <dt class="text-gray-500">Result</dt><dd class="col-span-2"><x-admin.badge :value="$detail->result" /></dd>
                <dt class="text-gray-500">Message</dt><dd class="col-span-2">{{ $detail->message }}</dd>
                <dt class="text-gray-500">Product</dt><dd class="col-span-2">{{ $detail->product?->name ?? '—' }}</dd>
                <dt class="text-gray-500">IP</dt><dd class="col-span-2 font-mono">{{ $detail->ip }}</dd>
                <dt class="text-gray-500">User agent</dt><dd class="col-span-2 break-all text-xs">{{ $detail->user_agent }}</dd>
            </dl>
            <div class="mt-4 text-sm text-gray-500">Request payload</div>
            <pre class="mt-1 max-h-72 overflow-auto rounded bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($detail->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </x-admin.dialog>
    @endif
</div>
