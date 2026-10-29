@props([
    'tabs' => [],
    'active' => null,
])

<div {{ $attributes->class(['app-tabs']) }} data-tabs-container>
    <ul class="nav nav-tabs app-tabs__nav mb-4" role="tablist">
        @foreach($tabs as $id => $tab)
            @php
                $tabId = is_string($id) ? $id : ($tab['id'] ?? 'tab-'.$loop->index);
                $isActive = $active ? ($active === $tabId) : $loop->first;
            @endphp
            <li class="nav-item" role="presentation">
                <button
                    class="nav-link {{ $isActive ? 'active' : '' }}"
                    id="{{ $tabId }}-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#{{ $tabId }}"
                    type="button"
                    role="tab"
                    aria-controls="{{ $tabId }}"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                >
                    @if(!empty($tab['icon']))
                        <i class="bi {{ $tab['icon'] }} me-1" aria-hidden="true"></i>
                    @endif
                    <span>{{ $tab['label'] }}</span>
                    @if(isset($tab['badge']) && $tab['badge'] !== null)
                        <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content app-tabs__content">
        {{ $slot }}
    </div>
</div>

