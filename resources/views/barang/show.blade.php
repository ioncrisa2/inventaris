@extends('layouts.app')

@section('title', 'Detail Barang - Sistem Inventaris & Kepegawaian')

@section('content')
@php
$kondisi = $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa';
$tahunList = array_keys($jadwalPenyusutan);
$tahunSebelumWindow = array_filter($tahunList, fn ($t) => $t < $tahunBerjalan - 2);
$tahunSesudahWindow = array_filter($tahunList, fn ($t) => $t > $tahunBerjalan + 2);
$chartData = [
    'labels' => $tahunList,
    'values' => array_map(fn ($t) => (float) $jadwalPenyusutan[$t]['nilai_buku_akhir_tahun'], $tahunList),
    'currentYearIndex' => array_search($tahunBerjalan, $tahunList),
];
$lampiranCount = $barang->fotoPendukung->count() + $barang->dokumen->count();
@endphp

<x-app-page>
    <x-page-header
        :title="$barang->nama_barang"
        :back-url="route('barang.index')"
    >
        <x-slot:subtitle>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span><strong>{{ $barang->kode_barang }}</strong></span>
                <span class="text-secondary">&middot;</span>
                <span>{{ $barang->kategori }}</span>
                <span class="text-secondary">&middot;</span>
                <x-badge :color="config('inventaris.kondisi_warna')[$kondisi] ?? 'bg-secondary'">{{ $kondisi }}</x-badge>
            </div>
        </x-slot:subtitle>

        <x-slot:actions>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-printer me-1"></i> Cetak
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <a class="dropdown-item" href="{{ route('barang.barcode', $barang) }}" target="_blank">
                                <i class="bi bi-upc-scan me-2 text-body-secondary"></i> Cetak Barcode
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('barang.qr-code', $barang) }}" target="_blank">
                                <i class="bi bi-qr-code me-2 text-body-secondary"></i> Cetak QR Code
                            </a>
                        </li>
                    </ul>
                </div>
                @can('update', $barang)
                    <a class="btn btn-primary" href="{{ route('barang.edit', $barang) }}">
                        <i class="bi bi-pencil me-1"></i> Edit Data
                    </a>
                @endcan
            </div>
        </x-slot:actions>
    </x-page-header>

    <x-flash-alert />

    {{-- Kartu Ringkasan (A2, A8 - Selalu terlihat di atas tab, responsive 2x2 di mobile) --}}
    <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
        <div class="col">
            <div class="card summary-card summary-card--plain h-100">
                <div class="card-body">
                    <div class="card-icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
                    <div class="card-info">
                        <div class="card-title">Nilai Perolehan</div>
                        <div class="card-value is-compact">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card summary-card summary-card--plain h-100">
                <div class="card-body">
                    <div class="card-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></div>
                    <div class="card-info">
                        <div class="card-title">Nilai Buku ({{ $tahunBerjalan }})</div>
                        <div class="card-value is-compact">Rp {{ number_format($nilaiBukuSaatIni, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card summary-card summary-card--plain h-100">
                <div class="card-body">
                    <div class="card-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                    <div class="card-info">
                        <div class="card-title">Sisa Masa Manfaat</div>
                        <div class="card-value is-compact">{{ $sisaTahun }} tahun</div>
                        <div class="small text-body-secondary mt-1">{{ $persentaseNilaiTersisa }}% nilai tersisa</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card summary-card summary-card--plain h-100">
                <div class="card-body">
                    <div class="card-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                    <div class="card-info">
                        <div class="card-title">Kelengkapan Data</div>
                        <div class="card-value is-compact">{{ $kelengkapan['persentase'] }}%</div>
                        @if(!empty($kelengkapan['item_belum_lengkap']))
                            <div class="small text-danger mt-1 text-truncate" title="Belum lengkap: {{ implode(', ', $kelengkapan['item_belum_lengkap']) }}">
                                Kurang {{ count($kelengkapan['item_belum_lengkap']) }} item
                            </div>
                        @else
                            <div class="small text-success mt-1">Data lengkap</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs (A3) --}}
    <x-tabs
        data-barang-tabs
        :tabs="[
            'tab-informasi' => ['label' => 'Informasi', 'icon' => 'bi-info-circle'],
            'tab-penyusutan' => ['label' => 'Penyusutan', 'icon' => 'bi-graph-down'],
            'tab-kondisi' => ['label' => 'Riwayat Kondisi', 'icon' => 'bi-clock-history', 'badge' => $riwayatKondisi->count() ?: null],
            'tab-lampiran' => ['label' => 'Lampiran', 'icon' => 'bi-paperclip', 'badge' => $lampiranCount ?: null],
        ]"
    >
        {{-- Tab 1: Informasi (A4) --}}
        <div class="tab-pane fade show active" id="tab-informasi" role="tabpanel" aria-labelledby="tab-informasi-tab">
            <div class="card section-card">
                <div class="card-body">
                    {{-- Thumbnail Foto Utama --}}
                    <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-body-tertiary rounded-3">
                        @if($fotoUtamaUrl)
                            <img src="{{ $fotoUtamaUrl }}" alt="Foto {{ $barang->nama_barang }}" class="asset-thumbnail" onerror="this.onerror=null;this.src='{{ asset('assets/img/no-image.svg') }}';">
                        @else
                            <div class="asset-thumbnail--placeholder" aria-label="Belum ada foto">
                                <img src="{{ asset('assets/img/no-image.svg') }}" alt="No Image">
                            </div>
                        @endif
                        <div>
                            <div class="fw-bold fs-6">{{ $barang->kode_barang }}</div>
                            <div class="text-body-secondary small">{{ $barang->jenis_barang ?? $barang->kategori }}</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        {{-- Kolom 1: Identitas & Lokasi --}}
                        <div class="col-lg-6">
                            <h3 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Identitas & Lokasi</h3>
                            <x-detail-list class="detail-list--single">
                                <x-detail-item label="Kode Barang" :value="$barang->kode_barang" emphasis />
                                <x-detail-item label="Jenis Barang" :value="$barang->jenis_barang ?? 'Belum diklasifikasikan'" />
                                <x-detail-item label="Golongan" :value="$barang->kategori" />
                                @if(auth()->user()->isSuperAdmin())
                                    <x-detail-item label="Koperasi" :value="$barang->koperasi?->nama ?? 'Tanpa koperasi'" />
                                @endif
                                <x-detail-item label="Unit Kerja" :value="$barang->unitKerja?->nama_unit ?? 'Belum ditentukan'" />
                                <x-detail-item label="Lokasi Penempatan" :value="$barang->lokasi_penempatan" />
                                @if(filled($barang->keterangan_lokasi) && trim((string) $barang->keterangan_lokasi) !== trim((string) $barang->lokasi_penempatan))
                                    <x-detail-item label="Keterangan Lokasi" :value="$barang->keterangan_lokasi" />
                                @endif
                            </x-detail-list>
                        </div>

                        {{-- Kolom 2: Nilai & Masa Manfaat --}}
                        <div class="col-lg-6">
                            <h3 class="h6 text-uppercase text-body-secondary fw-bold mb-3">Nilai & Penyusutan</h3>
                            <x-detail-list class="detail-list--single">
                                <x-detail-item label="Tanggal Perolehan" :value="$barang->tanggal_perolehan->translatedFormat('d F Y')" />
                                <x-detail-item label="Harga Perolehan" emphasis>Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</x-detail-item>
                                <x-detail-item label="Masa Manfaat" :value="$masaManfaatTahun.' tahun'" />
                                <x-detail-item label="Metode Penyusutan" :value="$metodePenyusutan" />
                                <x-detail-item label="Nilai Buku Saat Ini" emphasis>Rp {{ number_format($nilaiBukuSaatIni, 0, ',', '.') }}</x-detail-item>
                            </x-detail-list>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 2: Penyusutan (A5) --}}
        <div class="tab-pane fade" id="tab-penyusutan" role="tabpanel" aria-labelledby="tab-penyusutan-tab">
            {{-- Grafik Garis/Area Nilai Buku per Tahun --}}
            <div class="card section-card mb-4">
                <div class="card-header section-card__header">
                    <h2 class="h6 mb-0">Tren Nilai Buku per Tahun</h2>
                </div>
                <div class="card-body">
                    <div style="height: 250px; position: relative;">
                        <canvas id="chartPenyusutan" data-chart-penyusutan='@json($chartData)'></canvas>
                    </div>
                </div>
            </div>

            {{-- Tabel Ringkas Penyusutan --}}
            <x-section-card title="Rincian Penyusutan" flush>
                <x-slot:actions>
                    <a class="btn btn-sm btn-outline-success" href="{{ route('laporan.penyusutan.export', ['kategori' => $barang->kategori, 'tahun' => $tahunBerjalan]) }}" title="Export rincian penyusutan">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </a>
                </x-slot:actions>

                <div class="depreciation-table-wrapper table-responsive">
                    <table class="table table-hover align-middle mb-0 depreciation-table">
                        <thead>
                            <tr>
                                <th>Tahun</th>
                                <th class="text-end">Penyusutan Tahun Berjalan</th>
                                <th class="text-end">Akumulasi Penyusutan</th>
                                <th class="text-end">Nilai Buku Akhir Tahun</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($tahunSebelumWindow) > 0)
                            <tr>
                                <td colspan="4" class="text-center py-2 bg-body-tertiary">
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-depreciation-toggle-earlier>
                                        <i class="bi bi-caret-up-fill me-1"></i> Tampilkan {{ count($tahunSebelumWindow) }} tahun sebelumnya
                                    </button>
                                </td>
                            </tr>
                            @endif

                            @foreach($jadwalPenyusutan as $tahun => $r)
                            @php
                                $isEarlier = $tahun < $tahunBerjalan - 2;
                                $isLater = $tahun > $tahunBerjalan + 2;
                                $isCurrent = $tahun === $tahunBerjalan;
                                $isProjection = $tahun > $tahunBerjalan;
                            @endphp
                            <tr
                                @if($isEarlier) data-depreciation-earlier class="d-none" @endif
                                @if($isLater) data-depreciation-later class="d-none" @endif
                                @class([
                                    'table-active depreciation-row--current' => $isCurrent,
                                    'depreciation-row--projection' => $isProjection,
                                ])
                            >
                                <td>
                                    <strong>{{ $tahun }}</strong>
                                    @if($isCurrent)
                                        <x-badge color="bg-primary" class="ms-1">Berjalan</x-badge>
                                    @elseif($isProjection)
                                        <span class="badge bg-light text-body-secondary border ms-1">Proyeksi</span>
                                    @endif

                                    @if($r['is_prorata'])
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" title="{{ $r['keterangan_prorata'] }}">
                                            {{ $r['keterangan_prorata'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">Rp {{ number_format($r['penyusutan_tahun_ini'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($r['akumulasi_akhir_tahun'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($r['nilai_buku_akhir_tahun'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach

                            @if(count($tahunSesudahWindow) > 0)
                            <tr>
                                <td colspan="4" class="text-center py-2 bg-body-tertiary">
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none" data-depreciation-toggle-later>
                                        <i class="bi bi-caret-down-fill me-1"></i> Tampilkan {{ count($tahunSesudahWindow) }} tahun berikutnya
                                    </button>
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </x-section-card>
        </div>

        {{-- Tab 3: Riwayat Kondisi (A6) --}}
        <div class="tab-pane fade" id="tab-kondisi" role="tabpanel" aria-labelledby="tab-kondisi-tab">
            <div class="card section-card">
                <div class="card-body">
                    {{-- Header Riwayat & Total Biaya Perbaikan --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 p-3 bg-body-tertiary rounded-3">
                        <div>
                            <span class="text-body-secondary small d-block">Total Biaya Perbaikan:</span>
                            <strong class="fs-6 text-primary">Rp {{ number_format($totalBiayaPerbaikan, 0, ',', '.') }}</strong>
                        </div>
                        @can('catatKondisi', $barang)
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCatatKondisi">
                            <i class="bi bi-plus-circle me-1"></i> Catat Kondisi
                        </button>
                        @endcan
                    </div>

                    {{-- Timeline Vertikal --}}
                    @if($riwayatKondisi->isNotEmpty())
                        <ul class="condition-timeline">
                            @foreach($riwayatKondisi as $riwayat)
                            <li class="condition-timeline__item">
                                <div class="condition-timeline__marker"></div>
                                <div class="condition-timeline__content">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                        <div class="small text-body-secondary">
                                            <i class="bi bi-calendar3 me-1"></i> Tanggal Pencatatan: <strong>{{ $riwayat->tanggal_pemeriksaan->translatedFormat('d F Y') }}</strong>
                                        </div>
                                        <x-badge :color="config('inventaris.kondisi_warna')[$riwayat->kondisi] ?? 'bg-secondary'">{{ $riwayat->kondisi }}</x-badge>
                                    </div>
                                    <p class="mb-1 text-body">{{ $riwayat->keterangan ?: 'Tidak ada keterangan' }}</p>
                                    @if($riwayat->biaya_perbaikan !== null)
                                        <div class="small text-danger fw-semibold">
                                            <i class="bi bi-tools me-1"></i> Biaya Perbaikan: Rp {{ number_format($riwayat->biaya_perbaikan, 0, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <x-empty-state icon="bi-clock-history" title="Belum ada riwayat kondisi">
                            Pemeriksaan kondisi fisik belum pernah dicatat untuk barang ini.
                        </x-empty-state>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab 4: Lampiran (A7) --}}
        <div class="tab-pane fade" id="tab-lampiran" role="tabpanel" aria-labelledby="tab-lampiran-tab">
            {{-- Seksi Foto Pendukung --}}
            <x-section-card title="Foto Pendukung" class="mb-4">
                <x-slot:actions>
                    @can('kelolaFoto', $barang)
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadFoto">
                        <i class="bi bi-plus-circle me-1"></i> Upload Foto
                    </button>
                    @endcan
                </x-slot:actions>

                <div class="media-gallery">
                    @forelse($barang->fotoPendukung as $foto)
                    @php($fotoRegistry = $foto->storedFiles->first())
                    <div class="media-gallery__item">
                        @if(! $fotoRegistry || $fotoRegistry->isAvailable())
                        <x-image-preview
                            :src="\Illuminate\Support\Facades\Storage::url($foto->path)"
                            alt="Foto pendukung {{ $barang->nama_barang }}"
                            size="square"
                        />
                        @else
                        <div class="small text-body-secondary py-4 text-center" role="status">
                            <span class="spinner-border spinner-border-sm d-block mx-auto mb-2" aria-hidden="true"></span>
                            Sedang diproses
                        </div>
                        @endif
                        @if($foto->keterangan)
                        <div class="small text-body-secondary text-truncate mt-2" title="{{ $foto->keterangan }}">{{ $foto->keterangan }}</div>
                        @endif
                        @can('kelolaFoto', $barang)
                        <x-delete-button
                            :url="route('barang.foto.destroy', [$barang, $foto])"
                            message="Yakin ingin menghapus foto pendukung ini?"
                            label="Hapus foto pendukung"
                            class="mt-1"
                        />
                        @endcan
                    </div>
                    @empty
                    <x-empty-state icon="bi-images" title="Belum ada foto pendukung" class="w-100 text-center">
                        Unggah foto pendukung untuk melengkapi dokumentasi fisik barang.
                    </x-empty-state>
                    @endforelse
                </div>
            </x-section-card>

            {{-- Seksi Dokumen Pendukung --}}
            <x-section-card title="Dokumen Pendukung">
                <x-slot:actions>
                    @can('kelolaDokumen', $barang)
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadDokumen">
                        <i class="bi bi-plus-circle me-1"></i> Unggah Dokumen
                    </button>
                    @endcan
                </x-slot:actions>

                {{-- Checklist Dokumen Disarankan --}}
                <div class="mb-4">
                    <div class="small fw-semibold text-body-secondary mb-2">Checklist Dokumen Disarankan (Golongan: {{ $barang->kategori }}):</div>
                    <div class="doc-checklist">
                        @foreach($kelengkapan['checklist'] as $item)
                        @if($item['kategori'] === 'dokumen')
                        <div class="doc-checklist__item">
                            @if($item['terpenuhi'])
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <div>
                                    <strong>{{ $item['label'] }}</strong>
                                    <span class="badge bg-success-subtle text-success-emphasis ms-1">Tersedia</span>
                                </div>
                            @else
                                <i class="bi bi-exclamation-circle text-warning fs-5"></i>
                                <div>
                                    <strong>{{ $item['label'] }}</strong>
                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Belum Ada</span>
                                </div>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>

                {{-- Tabel Dokumen Terunggah --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Nama File</th>
                                <th>Tanggal Unggah</th>
                                <th class="text-nowrap table-col-width-120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($barang->dokumen as $dokumen)
                            @php($dokumenRegistry = $dokumen->storedFiles->first())
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $dokumen->jenis_dokumen }}</span></td>
                                <td>{{ $dokumen->nama_asli }}</td>
                                <td>{{ $dokumen->created_at->translatedFormat('d F Y') }}</td>
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        @if(! $dokumenRegistry || $dokumenRegistry->isAvailable())
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('barang.dokumen.download', [$barang, $dokumen]) }}"
                                            target="_blank"
                                            aria-label="Lihat {{ $dokumen->nama_asli }}"
                                            title="Lihat/Unduh">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @else
                                        <span class="small text-body-secondary" role="status">Sedang diproses</span>
                                        @endif
                                        @can('kelolaDokumen', $barang)
                                        <x-delete-button
                                            :url="route('barang.dokumen.destroy', [$barang, $dokumen])"
                                            :message="'Yakin ingin menghapus dokumen &quot;'.$dokumen->nama_asli.'&quot;?'"
                                            :label="'Hapus '.$dokumen->nama_asli"
                                        />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <x-empty-row :colspan="4">
                                <x-empty-state icon="bi-file-earmark-text" title="Belum ada dokumen" class="w-100 text-center">
                                    Unggah dokumen bukti perolehan, garansi, atau legalitas barang.
                                </x-empty-state>
                            </x-empty-row>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-section-card>
        </div>
    </x-tabs>
</x-app-page>

{{-- Modal Upload Dokumen --}}
<x-modal-form
    id="modalUploadDokumen"
    title="Unggah Dokumen"
    :action="route('barang.dokumen.store', $barang)"
    submit-label="Unggah"
>
    <div class="mb-3">
        <x-form.select
            name="jenis_dokumen"
            label="Jenis Dokumen"
            :options="collect(config('inventaris.jenis_dokumen'))->mapWithKeys(fn ($jenis) => [$jenis => $jenis])"
            required
            placeholder="Pilih jenis dokumen"
        />
    </div>

    <x-form.file name="dokumen" label="File Dokumen" policy="business_documents" required />
</x-modal-form>

{{-- Modal Upload Foto --}}
<x-modal-form
    id="modalUploadFoto"
    title="Upload Foto Pendukung"
    :action="route('barang.foto.store', $barang)"
    submit-label="Unggah"
>
    <x-form.file name="foto" label="File Foto" policy="asset_photo" required />
    <div class="mt-3">
        <x-form.input name="keterangan" id="foto_keterangan" label="Keterangan" maxlength="255" help="Opsional." />
    </div>
</x-modal-form>

{{-- Modal Catat Kondisi --}}
<x-modal-form
    id="modalCatatKondisi"
    :data-auto-show-modal="$errors->any()"
    dialog-class="modal-dialog-centered"
    :action="route('barang.kondisi.store', $barang)"
    submit-label="Simpan Pemeriksaan"
>
    <x-slot:header>
        <div>
            <h2 class="modal-title fs-5" id="modalCatatKondisiLabel">Catat Kondisi Barang</h2>
            <div class="text-muted small">{{ $barang->kode_barang }} &mdash; {{ $barang->nama_barang }}</div>
        </div>
    </x-slot:header>

    <div class="mb-3">
        <x-form.input
            name="tanggal_pemeriksaan"
            label="Tanggal Pencatatan"
            type="date"
            :value="now()->toDateString()"
            required
            :min="$barang->tanggal_perolehan->format('Y-m-d')"
            :max="now()->toDateString()"
        />
    </div>

    <div class="mb-3">
        <x-form.select
            name="kondisi"
            label="Kondisi"
            :options="collect(config('inventaris.kondisi'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
            :value="$kondisi"
            required
        />
    </div>

    <div class="mb-3">
        <label for="keterangan" class="form-label">Keterangan</label>
        <textarea
            name="keterangan"
            id="keterangan"
            class="form-control @error('keterangan') is-invalid @enderror"
            rows="3"
            maxlength="1000"
            placeholder="Contoh: layar berkedip dan perlu pemeriksaan teknisi">{{ old('keterangan') }}</textarea>
        @error('keterangan')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <x-form.money-input name="biaya_perbaikan" label="Biaya Perbaikan" placeholder="Opsional" />
</x-modal-form>
@endsection
