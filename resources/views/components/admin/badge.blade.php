@props(['value'])

@php
    $colors = [
        'active' => 'bg-green-100 text-green-800',
        'success' => 'bg-green-100 text-green-800',
        'published' => 'bg-green-100 text-green-800',
        'yes' => 'bg-green-100 text-green-800',
        'suspended' => 'bg-yellow-100 text-yellow-800',
        'draft' => 'bg-yellow-100 text-yellow-800',
        'expired' => 'bg-gray-200 text-gray-700',
        'inactive' => 'bg-gray-200 text-gray-700',
        'no' => 'bg-gray-200 text-gray-700',
        'revoked' => 'bg-red-100 text-red-800',
        'failed' => 'bg-red-100 text-red-800',
        'internal' => 'bg-purple-100 text-purple-800',
        'external' => 'bg-blue-100 text-blue-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($colors[$value] ?? 'bg-gray-100 text-gray-700')]) }}>
    {{ $slot->isEmpty() ? ucfirst($value) : $slot }}
</span>
