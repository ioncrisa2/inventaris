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

    @if (auth()->user()->koperasi_id !== null)
        @php
            $storageUsage = app(\App\Services\StorageUsageService::class)->tenantUsage(auth()->user()->koperasi_id);
            $quotaBytes = \App\Services\StorageUsageService::TENANT_QUOTA_BYTES;
            $percentage = min(100, round(($storageUsage / max(1, $quotaBytes)) * 100));
            $barColor = $percentage >= 90 ? 'bg-danger' : ($percentage >= 75 ? 'bg-warning' : 'bg-primary');
            $usageMB = number_format($storageUsage / 1048576, 1, ',', '.');
        @endphp
        <div class="mt-auto px-2 pt-3 pb-1 w-100 sidebar-quota border-top">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold text-body-secondary"><i class="bi bi-cloud-arrow-up me-1"></i> Penyimpanan</span>
                <span class="small text-body-secondary">{{ $percentage }}%</span>
            </div>
            <div class="progress" style="height: 6px;" aria-label="Penggunaan Penyimpanan" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ $barColor }}" style="width: {{ $percentage }}%"></div>
            </div>
            <div class="small text-body-secondary mt-1">
                {{ $usageMB }} MB / 1 GB digunakan
            </div>
        </div>
    @endif
</nav>
