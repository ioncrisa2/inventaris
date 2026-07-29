@props([
    'title' => null,
    'subtitle' => null,
    'paginator' => null,
])

<section {{ $attributes->class(['data-table-section']) }}>
    @if ($title || isset($toolbar))
        <div class="data-table-section__header">
            @if ($title)
                <div class="data-table-section__heading">
                    <h2>{{ $title }}</h2>
                    @if ($subtitle)
                        <p>{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @isset($toolbar)
                <div class="data-table-section__toolbar">
                    {{ $toolbar }}
                </div>
            @endisset
        </div>
    @endif

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
</section>
