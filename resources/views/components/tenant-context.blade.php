@php
    $user = auth()->user();
    $isOwner = $user->isSystemOwner();
    $isSuperAdmin = $user->isSuperAdmin();
    $isGlobal = $user->isPlatformAccount();
    $isControlPlane = $isSuperAdmin && request()->routeIs(['koperasi.*', 'pengguna.*', 'role.*']);
    $tenantName = $isOwner
        ? 'Platform global'
        : ($isSuperAdmin
            ? 'Seluruh koperasi'
            : $user->koperasi?->nama ?? 'Koperasi tidak terhubung');
    $accessMode = $isOwner ? 'Observasi agregat' : ($isControlPlane ? 'Kelola sistem' : 'Baca data');
@endphp

<div {{ $attributes->class(['tenant-context', 'tenant-context--global' => $isGlobal, 'tenant-context--invalid' => !$isGlobal && !$user->koperasi_id]) }}
    role="status" aria-label="Konteks akses: {{ $tenantName }}{{ $isGlobal ? ', ' . strtolower($accessMode) : '' }}">
    <i class="bi {{ $isOwner ? 'bi-shield-check' : ($isGlobal ? 'bi-buildings' : 'bi-building') }}"
        aria-hidden="true"></i>
    <span class="tenant-context__copy">
        <span class="tenant-context__name">{{ $tenantName }}</span>
        @if ($isGlobal)
            <span class="tenant-context__mode">{{ $accessMode }}</span>
        @endif
    </span>
</div>
