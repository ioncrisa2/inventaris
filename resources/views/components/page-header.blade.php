@props(['title', 'subtitle' => null])

<header class="page-header">
    <div class="page-header__heading">
        <h1>{{ $title }}</h1>
        @if($subtitle)
        <p>{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
    <div class="page-header__actions">{{ $actions }}</div>
    @endisset
</header>
