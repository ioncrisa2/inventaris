@props([
    'title' => null,
    'subtitle' => null,
    'paginator' => null,
])

<section {{ $attributes->class(['card data-table-card']) }}>
    @if($title || isset($toolbar))
    <div class="card-header data-table-card__header">
        @if($title)
        <div class="data-table-card__heading">
            <h2>{{ $title }}</h2>
            @if($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
        @endif

        @isset($toolbar)
            <div class="data-table-card__toolbar">
                {{ $toolbar }}
            </div>
        @endisset
    </div>
    @endif

    @isset($bulkActions)
        <div class="data-table-card__bulk">
            {{ $bulkActions }}
        </div>
    @endisset

    <div class="table-responsive">
        {{ $slot }}
    </div>

    @if($paginator)
        <x-pagination-footer :paginator="$paginator" />
    @endif
</section>
