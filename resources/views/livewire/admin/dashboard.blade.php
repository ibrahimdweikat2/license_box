<div>
    <x-admin.page-header title="Dashboard" subtitle="Overview of your licenses, installations and API traffic." />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
        @foreach ($stats as $label => $value)
            <div class="card p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold {{ str_starts_with($label, 'Failed') && $value ? 'text-red-600' : '' }}">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="card mt-6 p-6">
        <div class="flex items-baseline justify-between">
            <h2 class="font-semibold">New activations</h2>
            <span class="text-xs text-gray-500">Last 30 days · {{ $chart->sum('count') }} total</span>
        </div>
        <div class="mt-4 flex h-40 items-end gap-1" role="img" aria-label="Bar chart of new activations per day for the last 30 days">
            @foreach ($chart as $day)
                <div class="group relative flex-1 h-full flex items-end">
                    <div class="w-full rounded-t {{ $day['count'] ? 'bg-indigo-500 group-hover:bg-indigo-600' : 'bg-gray-200' }}"
                         style="height: {{ $day['count'] ? max(4, round($day['count'] / $chartMax * 100)) : 2 }}%"></div>
                    <div class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100">
                        {{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}: {{ $day['count'] }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-xs text-gray-400">
            <span>{{ \Illuminate\Support\Carbon::parse($chart->first()['date'])->format('M j') }}</span>
            <span>Today</span>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h2 class="font-semibold">Recent API activity</h2>
                <a href="{{ route('admin.logs') }}" wire:navigate class="text-sm link">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($recent as $log)
                            <tr>
                                <td class="font-mono text-xs">{{ $log->action }}</td>
                                <td><x-admin.badge :value="$log->result" /></td>
                                <td class="text-gray-500 truncate max-w-[12rem]">{{ $log->message }}</td>
                                <td class="text-gray-400 text-xs whitespace-nowrap">{{ $log->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <x-admin.empty colspan="4">No API calls yet.</x-admin.empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h2 class="font-semibold">Expiring in the next 30 days</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($expiring as $license)
                            <tr>
                                <td><a href="{{ route('admin.licenses.show', $license) }}" wire:navigate class="font-mono text-xs link">{{ $license->license_key }}</a></td>
                                <td class="text-gray-600">{{ $license->client?->name ?? '—' }}</td>
                                <td class="text-gray-500 text-xs whitespace-nowrap">{{ $license->expires_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <x-admin.empty colspan="3">No licenses expire soon.</x-admin.empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
