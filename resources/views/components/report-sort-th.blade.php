@props([
    'column',
    'label',
    'align' => 'start',
    'defaultDirection' => 'asc',
])

@php
    $currentSort = request('sort');
    $currentDirection = request('direction', 'asc');
    $isActive = $currentSort === $column;

    if ($isActive) {
        $nextDirection = $currentDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $nextDirection = $defaultDirection;
    }

    $sortUrl = request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => 1,
    ]);

    $alignClass = match ($align) {
        'end' => 'text-end',
        'center' => 'text-center',
        default => 'text-start',
    };
@endphp

<th scope="col" class="{{ $alignClass }}" @if($isActive) aria-sort="{{ $currentDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $sortUrl }}" class="report-sort-link {{ $isActive ? 'is-active' : '' }} {{ $align === 'end' ? 'flex-row-reverse' : '' }}">
        <span>{{ $label }}</span>
        @if ($isActive)
            <i class="bi bi-arrow-{{ $currentDirection === 'asc' ? 'up' : 'down' }} fw-bold"></i>
        @else
            <i class="bi bi-arrow-down-up"></i>
        @endif
    </a>
</th>

