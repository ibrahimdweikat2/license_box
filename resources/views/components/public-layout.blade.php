@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ \App\Models\Setting::get('site_name') }}</title>
        <meta name="description" content="Self-hosted license management and update server for PHP applications.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white text-gray-900">
        <header class="border-b border-gray-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-lg">
                    <x-application-logo class="h-7 w-7 fill-current text-indigo-600" />
                    {{ \App\Models\Setting::get('site_name') }}
                </a>
                <nav class="flex items-center gap-4 sm:gap-6 text-sm">
                    <a href="{{ route('home') }}#features" class="hidden sm:inline text-gray-600 hover:text-gray-900">Features</a>
                    <a href="{{ route('docs') }}" class="text-gray-600 hover:text-gray-900">Docs</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">Log in</a>
                    @endauth
                </nav>
            </div>
        </header>

        {{ $slot }}

        <footer class="border-t border-gray-100 mt-16">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 text-sm text-gray-500 flex flex-col sm:flex-row justify-between gap-2">
                <span>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('site_name') }}</span>
                <span>Self-hosted license &amp; update server</span>
            </div>
        </footer>
    </body>
</html>
