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
            <option value="persentase_pengali" @selected($baris['metode'] === 'persentase_pengali')>Persentase × Pengali</option>
            <option value="per_hari" @selected($baris['metode'] === 'per_hari')>Per Hari Hadir (Periode Gaji)</option>
            <option value="harian_sehari" @selected($baris['metode'] === 'harian_sehari')>Harian (Sehari)</option>
            <option value="harian_manual" @selected($baris['metode'] === 'harian_manual')>Harian (Dikali Jumlah Hari)</option>
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
        <div class="salary-date-range mt-2 {{ $baris['metode'] === 'per_hari' ? '' : 'd-none' }}" id="{{ $idAwalan }}_rentang">
            <x-form.date-range
                name-awal="{{ $namaField }}[tanggal_awal]"
                name-akhir="{{ $namaField }}[tanggal_akhir]"
                label="Periode"
                :value-awal="$baris['tanggal_awal'] ?? null"
                :value-akhir="$baris['tanggal_akhir'] ?? null"
            />
            <span class="form-text d-block mt-1">Hanya absensi berstatus Hadir dalam periode ini yang dihitung.</span>
        </div>
        <div class="salary-single-date mt-2 {{ $baris['metode'] === 'harian_sehari' ? '' : 'd-none' }}" id="{{ $idAwalan }}_tanggal_tunggal">
            <x-form.input
                name="{{ $namaField }}[tanggal]"
                type="date"
                label="Tanggal"
                :value="$baris['tanggal'] ?? null"
            />
        </div>
        <div class="salary-jumlah-hari mt-2 {{ $baris['metode'] === 'harian_manual' ? '' : 'd-none' }}" id="{{ $idAwalan }}_jumlah_hari">
            <x-form.input
                name="{{ $namaField }}[jumlah_hari]"
                type="number"
                label="Jumlah Hari"
                :value="$baris['jumlah_hari'] ?? null"
                min="1"
                max="366"
            />
        </div>
        <div class="salary-jumlah-pengali mt-2 {{ $baris['metode'] === 'persentase_pengali' ? '' : 'd-none' }}" id="{{ $idAwalan }}_jumlah_pengali">
            <x-form.input name="{{ $namaField }}[jumlah_pengali]" type="number" label="Jumlah Pengali" :value="$baris['jumlah_pengali'] ?? null" min="1" max="65535" />
        </div>
    </td>
    @else
    <td>
        {{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$baris['metode']] ?? $baris['metode'] }}
    </td>
    <td>
        @if($baris['metode'] === 'persentase')
            {{ rtrim(rtrim($baris['nilai'], '0'), '.') }}%
        @elseif($baris['metode'] === 'persentase_pengali')
            {{ rtrim(rtrim($baris['nilai'], '0'), '.') }}% dari gaji pokok
            <div class="salary-jumlah-pengali mt-2">
                <x-form.input name="{{ $namaField }}[jumlah_pengali]" type="number" label="Jumlah Pengali" :value="$baris['jumlah_pengali'] ?? null" min="1" max="65535" required />
            </div>
        @elseif($baris['metode'] === 'per_hari')
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }} /hari
            <div class="salary-date-range mt-2">
                <x-form.date-range
                    name-awal="{{ $namaField }}[tanggal_awal]"
                    name-akhir="{{ $namaField }}[tanggal_akhir]"
                    label="Periode"
                    :value-awal="$baris['tanggal_awal'] ?? null"
                    :value-akhir="$baris['tanggal_akhir'] ?? null"
                    required
                />
                <span class="form-text d-block mt-1">Hanya absensi berstatus Hadir dalam periode ini yang dihitung.</span>
            </div>
        @elseif($baris['metode'] === 'harian_sehari')
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }} /hari
            <div class="salary-single-date mt-2">
                <x-form.input
                    name="{{ $namaField }}[tanggal]"
                    type="date"
                    label="Tanggal"
                    :value="$baris['tanggal'] ?? null"
                    required
                />
            </div>
        @elseif($baris['metode'] === 'harian_manual')
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }} /hari
            <div class="salary-jumlah-hari mt-2">
                <x-form.input
                    name="{{ $namaField }}[jumlah_hari]"
                    type="number"
                    label="Jumlah Hari"
                    :value="$baris['jumlah_hari'] ?? null"
                    min="1"
                    max="366"
                    required
                />
            </div>
        @else
            Rp {{ number_format($baris['nilai'], 0, ',', '.') }}
        @endif
    </td>
    @endif
</tr>

@once
@endonce
