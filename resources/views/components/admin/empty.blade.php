@props(['colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-sm text-gray-500">{{ $slot }}</td>
</tr>
