@php
    $idAwalan = 'baris_'.$baris['kunci'];
    $namaField = "baris[{$baris['kunci']}]";
    $rincian = collect($baris['rincian'] ?? []);
@endphp
<tr data-salary-row>
    <td class="align-top pt-3">
        <input
            type="checkbox"
            class="form-check-input"
            name="{{ $namaField }}[pakai]"
            value="1"
            id="{{ $idAwalan }}_pakai"
            @checked($baris['checked'])
            data-salary-row-toggle
            aria-label="Gunakan komponen {{ $baris['nama_komponen'] }}"
        >
    </td>
    <td class="align-top pt-3">
        <label for="{{ $idAwalan }}_pakai" class="fw-semibold">{{ $baris['nama_komponen'] }}</label>
        @if($bisaHapusNama)
            <span class="text-body-secondary small d-block">Komponen sudah dihapus dari master</span>
            <input type="hidden" name="{{ $namaField }}[nama_komponen_snapshot]" value="{{ $baris['nama_komponen'] }}">
            <input type="hidden" name="{{ $namaField }}[jenis_snapshot]" value="{{ $baris['jenis'] }}">
        @endif
    </td>

    @if($bisaHapusNama)
        <td class="align-top">
            <select name="{{ $namaField }}[metode_perhitungan]" id="{{ $idAwalan }}_metode" class="form-select form-select-sm" data-salary-calculation-method>
                @foreach(\App\Models\KomponenGaji::METODE_PERHITUNGAN as $nilaiMetode => $labelMetode)
                    <option value="{{ $nilaiMetode }}" @selected($baris['metode'] === $nilaiMetode)>{{ $labelMetode }}</option>
                @endforeach
            </select>
        </td>
        <td class="align-top">
            <div class="input-group input-group-sm">
                <span class="input-group-text" id="{{ $idAwalan }}_prefix">Rp</span>
                <input
                    type="number"
                    name="{{ $namaField }}[nilai]"
                    id="{{ $idAwalan }}_nilai"
                    class="form-control @error("baris.{$baris['kunci']}.nilai") is-invalid @enderror"
                    value="{{ $baris['nilai'] }}"
                    min="0"
                    step="0.01"
                    aria-label="Nilai {{ $baris['nama_komponen'] }}"
                >
                <span class="input-group-text d-none" id="{{ $idAwalan }}_suffix">%</span>
            </div>
            @error("baris.{$baris['kunci']}.nilai")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <div class="salary-list-description mt-2 {{ $baris['metode'] === 'nominal_tetap_list' ? '' : 'd-none' }}" id="{{ $idAwalan }}_keterangan">
                <label for="{{ $idAwalan }}_keterangan_input" class="form-label small mb-1">Keterangan</label>
                <input
                    type="text"
                    name="{{ $namaField }}[keterangan]"
                    id="{{ $idAwalan }}_keterangan_input"
                    class="form-control form-control-sm @error("baris.{$baris['kunci']}.keterangan") is-invalid @enderror"
                    value="{{ $baris['keterangan'] }}"
                    maxlength="255"
                >
                @error("baris.{$baris['kunci']}.keterangan")<div class="invalid-feedback">{{ $message }}</div>@enderror
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
            <div class="salary-jumlah-hari mt-2 {{ $baris['metode'] === 'harian_manual' ? '' : 'd-none' }}" id="{{ $idAwalan }}_jumlah_hari">
                <x-form.input name="{{ $namaField }}[jumlah_hari]" type="number" label="Jumlah Hari" :value="$baris['jumlah_hari'] ?? null" min="1" max="366" />
            </div>
            <div class="salary-jumlah-pengali mt-2 {{ $baris['metode'] === 'persentase_pengali' ? '' : 'd-none' }}" id="{{ $idAwalan }}_jumlah_pengali">
                <x-form.input name="{{ $namaField }}[jumlah_pengali]" type="number" label="Jumlah Pengali" :value="$baris['jumlah_pengali'] ?? null" min="1" max="65535" />
            </div>
        </td>
    @else
        <td class="align-top pt-3">
            {{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$baris['metode']] ?? $baris['metode'] }}
        </td>
        <td class="align-top">
            @if($baris['metode'] === 'nominal_tidak_tetap')
                <label for="{{ $idAwalan }}_nilai" class="form-label small mb-1">Nominal Transaksi</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <input
                        type="number"
                        name="{{ $namaField }}[nilai]"
                        id="{{ $idAwalan }}_nilai"
                        class="form-control @error("baris.{$baris['kunci']}.nilai") is-invalid @enderror"
                        value="{{ $baris['nilai'] }}"
                        min="0"
                        step="0.01"
                        placeholder="0"
                    >
                </div>
                @error("baris.{$baris['kunci']}.nilai")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Isi nominal yang berlaku khusus untuk transaksi ini.</div>
            @elseif($baris['metode'] === 'nominal_tetap_list')
                <span class="small fw-semibold d-block mb-1">Pilih Rincian Tetap</span>
                @forelse($rincian as $item)
                    <label class="d-flex align-items-start gap-2 py-2 border-bottom small">
                        <input
                            type="checkbox"
                            class="form-check-input mt-0"
                            name="{{ $namaField }}[rincian_ids][]"
                            value="{{ $item['id'] }}"
                            @checked($item['checked'])
                        >
                        <span class="d-flex justify-content-between gap-3 flex-grow-1">
                            <span>{{ $item['keterangan'] }}</span>
                            <span class="text-nowrap">Rp {{ number_format($item['nominal'], 0, ',', '.') }}</span>
                        </span>
                    </label>
                @empty
                    <span class="text-danger small">Belum ada rincian. Edit Komponen Gaji terlebih dahulu.</span>
                @endforelse
                @error("baris.{$baris['kunci']}.rincian_ids")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @elseif($baris['metode'] === 'persentase')
                {{ rtrim(rtrim($baris['nilai'], '0'), '.') }}%
            @elseif($baris['metode'] === 'persentase_pengali')
                {{ rtrim(rtrim($baris['nilai'], '0'), '.') }}% dari gaji pokok
                <div class="salary-jumlah-pengali mt-2">
                    <x-form.input name="{{ $namaField }}[jumlah_pengali]" type="number" label="Jumlah Pengali" :value="$baris['jumlah_pengali'] ?? null" min="1" max="65535" />
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
                    />
                    <span class="form-text d-block mt-1">Hanya absensi berstatus Hadir dalam periode ini yang dihitung.</span>
                </div>
            @elseif($baris['metode'] === 'harian_manual')
                Rp {{ number_format($baris['nilai'], 0, ',', '.') }} /hari
                <div class="salary-jumlah-hari mt-2">
                    <x-form.input name="{{ $namaField }}[jumlah_hari]" type="number" label="Jumlah Hari" :value="$baris['jumlah_hari'] ?? null" min="1" max="366" />
                </div>
            @else
                Rp {{ number_format($baris['nilai'], 0, ',', '.') }}
            @endif
        </td>
    @endif
</tr>
