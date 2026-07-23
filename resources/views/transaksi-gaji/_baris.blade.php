@php
    $idAwalan = 'baris_'.$baris['kunci'];
    $namaField = "baris[{$baris['kunci']}]";
@endphp
<tr>
    <td>
        <input
            type="checkbox"
            class="form-check-input"
            name="{{ $namaField }}[pakai]"
            value="1"
            id="{{ $idAwalan }}_pakai"
            @checked($baris['checked'])
        >
    </td>
    <td>
        {{ $baris['nama_komponen'] }}
        @if($bisaHapusNama)
            <span class="text-body-secondary small d-block">(komponen sudah dihapus dari master)</span>
            <input type="hidden" name="{{ $namaField }}[nama_komponen_snapshot]" value="{{ $baris['nama_komponen'] }}">
            <input type="hidden" name="{{ $namaField }}[jenis_snapshot]" value="{{ $baris['jenis'] }}">
        @endif
    </td>
    @if($bisaHapusNama)
    <td>
        <select name="{{ $namaField }}[metode_perhitungan]" id="{{ $idAwalan }}_metode" class="form-select form-select-sm" data-salary-calculation-method>
            <option value="nominal_tetap" @selected($baris['metode'] === 'nominal_tetap')>Nominal Tetap</option>
            <option value="persentase" @selected($baris['metode'] === 'persentase')>Persentase</option>
            <option value="per_kehadiran" @selected($baris['metode'] === 'per_kehadiran')>Per Kehadiran</option>
        </select>
    </td>
    <td>
        <div class="input-group input-group-sm">
            <span class="input-group-text" id="{{ $idAwalan }}_prefix">Rp</span>
            <input
                type="number"
                name="{{ $namaField }}[nilai]"
                id="{{ $idAwalan }}_nilai"
                class="form-control"
                value="{{ $baris['nilai'] }}"
                min="0"
                step="0.01"
            >
            <span class="input-group-text d-none" id="{{ $idAwalan }}_suffix">%</span>
        </div>
    </td>
    @else
    <td>
        {{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$baris['metode']] ?? $baris['metode'] }}
    </td>
    <td>
        @if($baris['metode'] === 'persentase')
            {{ rtrim(rtrim($baris['nilai'], '0'), '.') }}%
        @elseif($baris['metode'] === 'per_kehadiran')
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }} /hari
        @else
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }}
        @endif
        <span class="text-body-secondary small d-block">Ubah di Komponen Gaji</span>
    </td>
    @endif
</tr>

@once
@endonce

