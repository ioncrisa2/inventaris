@php
    $idAwalan = 'baris_'.$baris['kunci'];
    $namaField = "baris[{$baris['kunci']}]";
    $rincian = collect($baris['rincian'] ?? []);

    if ($baris['metode'] === 'nominal_tetap_list' && $rincian->isEmpty()) {
        $rincian = collect([['keterangan' => '', 'nominal' => '']]);
    }

    $nextRincianIndex = ((int) ($rincian->keys()->max() ?? -1)) + 1;
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
                <div
                    data-salary-list
                    data-next-index="{{ $nextRincianIndex }}"
                >
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="small fw-semibold">Rincian Nominal</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-salary-list-add>
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            Tambah Rincian
                        </button>
                    </div>

                    <div class="d-grid gap-2" data-salary-list-rows>
                        @foreach($rincian as $rincianIndex => $item)
                            <div class="border rounded p-2" data-salary-list-row>
                                <div class="row g-2 align-items-end">
                                    <div class="col-lg-6">
                                        <label for="{{ $idAwalan }}_rincian_{{ $rincianIndex }}_keterangan" class="form-label small mb-1">Keterangan</label>
                                        <input
                                            type="text"
                                            name="{{ $namaField }}[rincian][{{ $rincianIndex }}][keterangan]"
                                            id="{{ $idAwalan }}_rincian_{{ $rincianIndex }}_keterangan"
                                            class="form-control form-control-sm @error("baris.{$baris['kunci']}.rincian.{$rincianIndex}.keterangan") is-invalid @enderror"
                                            value="{{ $item['keterangan'] ?? '' }}"
                                            maxlength="255"
                                            placeholder="Contoh: Laki-laki"
                                        >
                                        @error("baris.{$baris['kunci']}.rincian.{$rincianIndex}.keterangan")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-5">
                                        <label for="{{ $idAwalan }}_rincian_{{ $rincianIndex }}_nominal" class="form-label small mb-1">Nominal</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Rp</span>
                                            <input
                                                type="number"
                                                name="{{ $namaField }}[rincian][{{ $rincianIndex }}][nominal]"
                                                id="{{ $idAwalan }}_rincian_{{ $rincianIndex }}_nominal"
                                                class="form-control @error("baris.{$baris['kunci']}.rincian.{$rincianIndex}.nominal") is-invalid @enderror"
                                                value="{{ $item['nominal'] ?? '' }}"
                                                min="0"
                                                step="0.01"
                                                placeholder="0"
                                            >
                                        </div>
                                        @error("baris.{$baris['kunci']}.rincian.{$rincianIndex}.nominal")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-1 d-grid">
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-salary-list-remove aria-label="Hapus rincian">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="form-text mb-0 mt-2 {{ $rincian->isEmpty() ? '' : 'd-none' }}" data-salary-list-empty>
                        Belum ada rincian. Tambahkan minimal satu baris saat komponen digunakan.
                    </p>
                    @error("baris.{$baris['kunci']}.rincian")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <template data-salary-list-template>
                        <div class="border rounded p-2" data-salary-list-row>
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-6">
                                    <label for="{{ $idAwalan }}_rincian___INDEX___keterangan" class="form-label small mb-1">Keterangan</label>
                                    <input type="text" name="{{ $namaField }}[rincian][__INDEX__][keterangan]" id="{{ $idAwalan }}_rincian___INDEX___keterangan" class="form-control form-control-sm" maxlength="255" placeholder="Contoh: Perempuan">
                                </div>
                                <div class="col-lg-5">
                                    <label for="{{ $idAwalan }}_rincian___INDEX___nominal" class="form-label small mb-1">Nominal</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" name="{{ $namaField }}[rincian][__INDEX__][nominal]" id="{{ $idAwalan }}_rincian___INDEX___nominal" class="form-control" min="0" step="0.01" placeholder="0">
                                    </div>
                                </div>
                                <div class="col-lg-1 d-grid">
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-salary-list-remove aria-label="Hapus rincian">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
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
