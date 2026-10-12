@props(['title', 'close' => 'closeForm', 'maxWidth' => 'max-w-2xl'])

{{-- Render inside @if; "close" is the Livewire method called by the backdrop, Esc and the X button. --}}
<div class="fixed inset-0 z-40 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.{{ $close }}()">
    <div class="fixed inset-0 bg-gray-900/50" wire:click="{{ $close }}"></div>
    <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
        <div class="relative w-full {{ $maxWidth }} card">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                <button type="button" wire:click="{{ $close }}" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-5">{{ $slot }}</div>
        </div>
    </div>
</div>
