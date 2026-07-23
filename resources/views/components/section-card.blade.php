@props([
    'title' => null,
    'subtitle' => null,
    'flush' => false,
])

<section {{ $attributes->class(['card section-card']) }}>
    @if($title || isset($actions))
        <div class="card-header section-card__header">
            <div>
                @if($title)
                    <h2>{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="section-card__actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    @if($flush)
        {{ $slot }}
    @else
        <div class="card-body">{{ $slot }}</div>
    @endif

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</section>
