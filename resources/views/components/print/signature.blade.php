@props(['label' => 'Mengetahui,', 'name' => null])

<div class="signature-row">
    <div></div>
    <div class="signature-box">
        <div>{{ $label }}</div>
        <div class="signature-space"></div>
        <div>({{ $name ?? '____________________' }})</div>
    </div>
</div>
