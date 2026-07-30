@props([
    'title' => null,
    'subtitle' => null,
    'paginator' => null,
])

<x-section-card :title="$title" :subtitle="$subtitle" flush {{ $attributes->class(['data-table-section']) }}>
    @isset($toolbar)
        <x-slot:actions>{{ $toolbar }}</x-slot:actions>
    @endisset

    @isset($bulkActions)
        <div class="data-table-section__bulk">
            {{ $bulkActions }}
        </div>
    @endisset

    <div class="table-responsive">
        {{ $slot }}
    </div>

    @if ($paginator)
        <x-pagination-footer :paginator="$paginator" />
    @endif
</x-section-card>
