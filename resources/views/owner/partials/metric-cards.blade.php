@props(['cards' => []])

@php
    $icons = [
        'koperasi_aktif' => 'bi-buildings',
        'akun_tenant' => 'bi-person-check',
        'total_barang' => 'bi-box-seam',
        'nilai_inventaris' => 'bi-wallet2',
        'karyawan_aktif' => 'bi-people',
        'total_absensi' => 'bi-calendar-check',
        'total_gaji_bersih' => 'bi-cash-stack',
    ];
    $formatValue = static function (array $card): string {
        $value = $card['nilai'] ?? 0;

        return ($card['format'] ?? 'angka') === 'rupiah'
            ? \App\Support\OwnerMetricFormatter::rupiah((string) $value)
            : number_format((int) $value, 0, ',', '.');
    };
@endphp

<div class="owner-metric-strip" aria-label="Ringkasan metrik">
    @foreach ($cards as $card)
        <article class="owner-metric">
            <span class="owner-metric__icon" aria-hidden="true">
                <i class="bi {{ $icons[$card['key']] ?? 'bi-activity' }}"></i>
            </span>
            <div>
                <p>{{ $card['label'] }}</p>
                <strong class="{{ ($card['format'] ?? null) === 'rupiah' ? 'owner-metric__value--money' : '' }}">
                    {{ $formatValue($card) }}
                </strong>
            </div>
        </article>
    @endforeach
</div>
