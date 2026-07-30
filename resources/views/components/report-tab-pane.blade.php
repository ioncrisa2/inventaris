@props([
    'id',
    'title',
    'description' => null,
    'active' => false,
])

<section
    {{ $attributes->class([
        'tab-pane fade report-tab-pane',
        'show active' => $active,
    ]) }}
    id="{{ $id }}"
    role="tabpanel"
    aria-labelledby="{{ $id }}-tab"
    tabindex="0"
>
    <header class="report-tab-pane__header">
        <h2>{{ $title }}</h2>
        @if($description)
            <p>{{ $description }}</p>
        @endif
    </header>

    <div class="report-tab-pane__body">
        {{ $slot }}
    </div>
</section>
