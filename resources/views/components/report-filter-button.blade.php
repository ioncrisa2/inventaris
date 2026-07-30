@props([
    'target',
    'activeCount' => 0,
])

@php
    $hasActiveFilters = (int) $activeCount > 0;
@endphp

<button
    type="button"
    {{ $attributes->class([
        'btn report-filter-button',
        'btn-primary' => $hasActiveFilters,
        'btn-outline-secondary' => ! $hasActiveFilters,
    ]) }}
    data-bs-toggle="modal"
    data-bs-target="#{{ $target }}"
    aria-controls="{{ $target }}"
    aria-haspopup="dialog"
>
    <i class="bi bi-funnel" aria-hidden="true"></i>
    <span>Filter</span>
    @if($hasActiveFilters)
        <span class="badge report-filter-button__count">{{ $activeCount }}</span>
        <span class="visually-hidden">filter tambahan aktif</span>
    @endif
</button>
