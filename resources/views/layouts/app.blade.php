<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Models\Setting::get('site_name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <div x-data="{ sidebar: false }" class="min-h-screen lg:flex">
            @include('layouts.navigation')

            <div class="flex-1 min-w-0">
                {{-- Mobile top bar --}}
                <div class="lg:hidden flex items-center justify-between bg-gray-900 text-white px-4 h-14">
                    <button type="button" @click="sidebar = true" class="p-2 -ml-2" aria-label="Open menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="font-semibold">{{ \App\Models\Setting::get('site_name') }}</span>
                    <span class="w-6"></span>
                </div>

                @isset($header)
                    <header class="bg-white shadow-sm">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Toasts: Livewire components call $this->dispatch('notify', message: '...', type: 'success'|'error') --}}
        <div x-data="{ toasts: [] }"
             x-on:notify.window="const t = { id: Date.now() + Math.random(), message: $event.detail.message, type: $event.detail.type ?? 'success' }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4000)"
             class="fixed bottom-4 right-4 z-50 space-y-2 w-80 max-w-[calc(100vw-2rem)]">
            @if (session('notify'))
                <div x-init="$dispatch('notify', { message: @js(session('notify')) })"></div>
            @endif
            <template x-for="toast in toasts" :key="toast.id">
                <div x-transition class="rounded-md shadow-lg px-4 py-3 text-sm text-white"
                     :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'" x-text="toast.message"></div>
            </template>
        </div>

        @livewireScripts
    </body>
</html>
