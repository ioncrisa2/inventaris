@props([
    'title',
])

<div {{ $attributes->class(['report-recap-card']) }}>
    <div class="card-header py-2 px-3">
        <span class="fw-semibold text-body-emphasis">{{ $title }}</span>
    </div>
    <div class="table-responsive">
        {{ $slot }}
    </div>
</div>

