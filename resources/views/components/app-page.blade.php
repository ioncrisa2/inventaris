@props(['longFooter' => false, 'width' => null])

<div class="main-content">
    <div {{ $attributes->class(['container-fluid app-page', "app-page--{$width}" => $width]) }}>
        {{ $slot }}

        <x-app-footer :long="$longFooter" />
    </div>
</div>
