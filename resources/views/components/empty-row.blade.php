@props(['colspan'])

<tr>
    <td colspan="{{ $colspan }}">
        <x-empty-state class="empty-state--compact" title="Tidak ada data">{{ $slot }}</x-empty-state>
    </td>
</tr>
