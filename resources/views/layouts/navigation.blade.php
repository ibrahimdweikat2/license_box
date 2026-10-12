@php
    $links = [
        ['dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
        ['admin.products', 'Products', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['admin.licenses', 'Licenses', 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
        ['admin.activations', 'Activations', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['admin.clients', 'Clients', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['admin.api-keys', 'API Keys', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['admin.logs', 'Activity Logs', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
        ['admin.sdk', 'Client SDK', 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
        ['admin.settings', 'Settings', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
    ];
@endphp

{{-- Backdrop on mobile --}}
<div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

<aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 text-gray-300 flex flex-col transform transition-transform lg:translate-x-0 lg:static lg:inset-auto lg:min-h-screen">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 h-16 px-6 text-white font-semibold text-lg border-b border-gray-800">
        <x-application-logo class="h-7 w-7 fill-current text-indigo-400" />
        <span class="truncate">{{ \App\Models\Setting::get('site_name') }}</span>
    </a>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        @foreach ($links as [$route, $label, $icon])
            @php $active = request()->routeIs($route, $route.'.*'); @endphp
            <a href="{{ route($route) }}" wire:navigate
               class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium {{ $active ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-indigo-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <div class="border-t border-gray-800 p-4">
        <div class="text-sm text-white truncate">{{ Auth::user()->name }}</div>
        <div class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</div>
        <div class="mt-3 flex gap-3 text-xs">
            <a href="{{ route('profile.edit') }}" class="hover:text-white">Profile</a>
            <a href="{{ route('docs') }}" class="hover:text-white" target="_blank">API docs</a>
            <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                @csrf
                <button type="submit" class="hover:text-white">Log out</button>
            </form>
        </div>
    </div>
</aside>
