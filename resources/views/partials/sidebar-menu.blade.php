@php
    $idPrefix = $idPrefix ?? 'default';
    $groups = \App\Support\NavigationMenu::visibleGroups(auth()->user());
@endphp

<nav class="nav nav-pills flex-column gap-1 sidebar-nav">
    <div class="sidebar-section-label text-uppercase text-body-secondary small fw-semibold px-3 mt-2 mb-1">Menu Utama</div>

    @foreach($groups as $group)
        @if($group['type'] === 'link')
            <a class="nav-link {{ $group['active'] ? 'active' : '' }}" href="{{ route($group['route']) }}">
                <i class="bi {{ $group['icon'] }}"></i><span>{{ $group['label'] }}</span>
            </a>
        @else
            <button
                type="button"
                class="nav-link sidebar-group-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $idPrefix }}-group-{{ $group['key'] }}"
                aria-expanded="true"
                aria-controls="{{ $idPrefix }}-group-{{ $group['key'] }}"
            >
                <span class="text-uppercase small fw-semibold">{{ $group['label'] }}</span>
                <i class="bi bi-chevron-down sidebar-group-icon"></i>
            </button>
            <div
                class="collapse show sidebar-group"
                id="{{ $idPrefix }}-group-{{ $group['key'] }}"
                data-group-key="{{ $group['key'] }}"
                data-group-active="{{ $group['active'] ? '1' : '0' }}"
            >
                @foreach($group['items'] as $item)
                    <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                        <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</nav>
