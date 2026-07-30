@props(['colspan'])

<tr>
    <td colspan="{{ $colspan }}">
        <x-empty-state class="empty-state--compact" title="Tidak ada data">
            {{ $slot }}
            @isset($action)
                <x-slot:action>{{ $action }}</x-slot:action>
            @endisset
        </x-empty-state>
    </td>
</tr>
