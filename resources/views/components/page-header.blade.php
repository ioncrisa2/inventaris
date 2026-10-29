@props(['title' => null, 'subtitle' => null, 'backUrl' => null])

<header class="page-header">
    <div class="page-header__heading">
        <div class="d-flex align-items-center gap-2">
            @if($backUrl)
                <a class="asset-header-back" href="{{ $backUrl }}" title="Kembali" aria-label="Kembali">
                    <i class="bi bi-arrow-left"></i>
                </a>
            @endif
            <h1>{{ $title }}</h1>
        </div>
        @if ($subtitle)
            @if(is_string($subtitle))
                <p>{{ $subtitle }}</p>
            @else
                <div class="page-header__subtitle mt-1">{{ $subtitle }}</div>
            @endif
        @endif
    </div>

    @isset($actions)
        <div class="page-header__actions">{{ $actions }}</div>
    @endisset
</header>
