@extends('layouts.app')

@section('title', 'Dashboard - Sistem Inventaris & Kepegawaian')

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Dashboard" subtitle="Kondisi operasional berdasarkan data terbaru." />
        @php
            $user = auth()->user();
            $hasAnyWidget = $user->canAny([
                'dashboard.total-inventaris.view',
                'dashboard.nilai-aset.view',
                'dashboard.perlu-perbaikan.view',
                'dashboard.karyawan-aktif.view',
                'dashboard.tren-absensi.view',
                'dashboard.kondisi-inventaris.view',
                'dashboard.data-belum-lengkap.view',
            ]);
        @endphp

        <x-dashboard-welcome-banner :user="$user" />

        @unless ($hasAnyWidget)
            <div class="card">
                <div class="card-body text-center text-body-secondary py-5">
                    Belum ada widget dashboard yang bisa ditampilkan untuk role Anda.
                    Hubungi admin untuk mengatur hak akses dashboard.
                </div>
            </div>
        @endunless

        <!-- Summary Cards -->
        @if (
            $user->canAny([
                'dashboard.total-inventaris.view',
                'dashboard.nilai-aset.view',
                'dashboard.perlu-perbaikan.view',
                'dashboard.karyawan-aktif.view',
            ]))
            <div class="row g-3 mb-4">
                @can('dashboard.total-inventaris.view')
                    <div class="col-sm-6 col-xl-3">
                        <x-stat-card icon="bi-box-seam" label="Total Inventaris" :value="number_format($totalBarang, 0, ',', '.')" plain />
                    </div>
                @endcan
                @can('dashboard.nilai-aset.view')
                    <div class="col-sm-6 col-xl-3">
                        <x-stat-card icon="bi-wallet2" label="Nilai Aset" :value="'Rp ' . number_format($totalNilaiInventaris, 0, ',', '.')" plain compact />
                    </div>
                @endcan
                @can('dashboard.perlu-perbaikan.view')
                    <div class="col-sm-6 col-xl-3">
                        <x-stat-card icon="bi-exclamation-triangle" label="Perlu Perbaikan" :value="number_format($barangPerluPerbaikan, 0, ',', '.')"
                            plain accent />
                    </div>
                @endcan
                @can('dashboard.karyawan-aktif.view')
                    <div class="col-sm-6 col-xl-3">
                        <x-stat-card icon="bi-people-fill" label="Karyawan Aktif" :value="number_format($karyawanAktif, 0, ',', '.')" plain />
                    </div>
                @endcan
            </div>
        @endif

        @if ($user->canAny(['dashboard.tren-absensi.view', 'dashboard.kondisi-inventaris.view']))
            <div class="row g-3 mb-4">
                @can('dashboard.tren-absensi.view')
                    <div class="col-xl-8">
                        <section class="card dashboard-chart-card h-100" aria-labelledby="trenAbsensiTitle">
                            <div class="card-header dashboard-widget-header">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h2 id="trenAbsensiTitle">Tren Absensi Periode Penggajian</h2>
                                        <span
                                            class="badge {{ $trenAbsensi['statusPeriode'] === 'Berjalan' ? 'text-bg-primary' : ($trenAbsensi['statusPeriode'] === 'Selesai' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                            {{ $trenAbsensi['statusPeriode'] }}
                                        </span>
                                    </div>
                                    <p>{{ $trenAbsensi['periode'] }}</p>
                                </div>
                                <div class="dashboard-widget-actions">
                                    <nav class="btn-group" aria-label="Navigasi periode absensi">
                                        <a class="btn btn-sm btn-outline-secondary"
                                            href="{{ route('dashboard', ['periode' => $trenAbsensi['periodeSebelumnyaQuery']]) }}"
                                            aria-label="Tampilkan periode sebelumnya" title="Periode sebelumnya">
                                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Periode berjalan</a>
                                        <a class="btn btn-sm btn-outline-secondary"
                                            href="{{ route('dashboard', ['periode' => $trenAbsensi['periodeBerikutnyaQuery']]) }}"
                                            aria-label="Tampilkan periode berikutnya" title="Periode berikutnya">
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </a>
                                    </nav>
                                    <label class="previous-period-toggle">
                                        <input type="checkbox" data-previous-period-toggle>
                                        <span>Bandingkan periode sebelumnya</span>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="attendance-summary" aria-label="Ringkasan absensi periode terpilih">
                                    @foreach (['Hadir', 'Izin', 'Sakit', 'Cuti', 'Dinas Luar Kota', 'Alpha'] as $status)
                                        <div class="attendance-summary__item">
                                            <span>{{ $status }}</span>
                                            <strong>{{ number_format($trenAbsensi['ringkasan'][$status], 0, ',', '.') }}</strong>
                                        </div>
                                    @endforeach
                                    <div class="attendance-summary__item">
                                        <span>Tingkat hadir</span>
                                        <strong>{{ number_format($trenAbsensi['persentaseHadir'], 1, ',', '.') }}%</strong>
                                    </div>
                                </div>
                                <div class="chart-container dashboard-attendance-chart">
                                    <canvas id="chartTrenAbsensi"
                                        data-labels='@json($trenAbsensi['labels'] ?? [])'
                                        data-series='@json($trenAbsensi['seri'] ?? [])'
                                        data-current-dates='@json($trenAbsensi['tanggalSekarang'] ?? [])'
                                        data-previous-dates='@json($trenAbsensi['tanggalSebelumnya'] ?? [])'
                                        data-previous-attendance='@json($trenAbsensi['hadirSebelumnya'] ?? [])'
                                        data-weekends='@json($trenAbsensi['akhirPekan'] ?? [])'
                                        aria-label="Grafik harian status absensi untuk {{ $trenAbsensi['periode'] }}"
                                        role="img">
                                    </canvas>
                                </div>
                            </div>
                        </section>
                    </div>
                @endcan

                @can('dashboard.kondisi-inventaris.view')
                    <div class="col-xl-4">
                        <section class="card h-100" aria-labelledby="kondisiInventarisTitle">
                            <div class="card-header dashboard-widget-header">
                                <div>
                                    <h2 id="kondisiInventarisTitle">Kondisi Inventaris</h2>
                                    <p>Berdasarkan pemeriksaan terakhir</p>
                                </div>
                            </div>
                            <div class="card-body">
                                @php
                                    $kondisiAktif = collect($kondisiInventaris)->filter(fn ($grup) => $grup['total'] > 0);
                                    $totalKondisi = $kondisiAktif->sum('total');
                                @endphp
                                @if($totalKondisi > 0)
                                    <div class="condition-stack" role="img" aria-label="Perbandingan kondisi inventaris">
                                        @foreach($kondisiAktif as $kunci => $grup)
                                            <span
                                                class="condition-stack__segment condition-stack__segment--{{ $kunci }}"
                                                style="width: {{ ($grup['total'] / $totalKondisi) * 100 }}%"
                                                title="{{ $grup['label'] }}: {{ $grup['total'] }}"></span>
                                        @endforeach
                                    </div>
                                    <div class="condition-list mt-3">
                                        @foreach ($kondisiAktif as $kunci => $grup)
                                            @can('barang.view')
                                                <a class="condition-list__item" href="{{ route('barang.index', ['kondisi' => $kunci]) }}">
                                            @else
                                                <div class="condition-list__item">
                                            @endcan
                                                <span>
                                                    <span class="condition-dot condition-dot--{{ $kunci }}"></span>
                                                    {{ $grup['label'] }}
                                                </span>
                                                <strong>{{ number_format($grup['total'], 0, ',', '.') }}</strong>
                                            @can('barang.view')
                                                </a>
                                            @else
                                                </div>
                                            @endcan
                                        @endforeach
                                    </div>
                                @else
                                    <p class="dashboard-quiet-state">Belum ada data kondisi inventaris.</p>
                                @endif
                            </div>
                        </section>
                    </div>
                @endcan
            </div>
        @endif

        @can('dashboard.data-belum-lengkap.view')
            <section class="card mb-4" aria-labelledby="dataBelumLengkapTitle">
                <div class="card-header dashboard-widget-header">
                    <div>
                        <h2 id="dataBelumLengkapTitle">Data Belum Lengkap</h2>
                        <p>Prioritas perbaikan data master yang memengaruhi laporan dan administrasi.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="data-quality-grid">
                        @php
                            $indikatorKelengkapan = collect([
                                [
                                    'key' => 'barangBelumDiperiksa',
                                    'label' => 'Barang belum diperiksa',
                                    'description' => 'Belum memiliki riwayat kondisi.',
                                    'icon' => 'bi-clipboard2-pulse',
                                    'route' => route('barang.index', ['kelengkapan' => 'belum-diperiksa']),
                                    'permission' => 'barang.view',
                                ],
                                [
                                    'key' => 'barangTanpaFoto',
                                    'label' => 'Barang tanpa foto',
                                    'description' => 'Foto sampul belum tersedia.',
                                    'icon' => 'bi-image',
                                    'route' => route('barang.index', ['kelengkapan' => 'tanpa-foto']),
                                    'permission' => 'barang.view',
                                ],
                                [
                                    'key' => 'barangTanpaNota',
                                    'label' => 'Barang tanpa nota',
                                    'description' => 'Dokumen pembelian belum diunggah.',
                                    'icon' => 'bi-receipt',
                                    'route' => route('barang.index', ['kelengkapan' => 'tanpa-nota']),
                                    'permission' => 'barang.view',
                                ],
                                [
                                    'key' => 'karyawanTidakLengkap',
                                    'label' => 'Data karyawan belum lengkap',
                                    'description' => 'Data inti, foto, atau dokumen KTP belum lengkap.',
                                    'icon' => 'bi-person-exclamation',
                                    'route' => route('karyawan.index', ['kelengkapan' => 'data-inti']),
                                    'permission' => 'karyawan.view',
                                ],
                            ])->map(fn ($indikator) => [
                                ...$indikator,
                                'count' => (int) ($dataBelumLengkap[$indikator['key']] ?? 0),
                            ])->filter(fn ($indikator) => $indikator['count'] > 0 && $user->can($indikator['permission']));
                        @endphp
                        @forelse ($indikatorKelengkapan as $indikator)
                                <a class="data-quality-item" href="{{ $indikator['route'] }}">
                                    <span class="data-quality-item__icon"><i class="bi {{ $indikator['icon'] }}"
                                            aria-hidden="true"></i></span>
                                    <span class="data-quality-item__content">
                                        <strong>{{ $indikator['label'] }}</strong>
                                        <small>{{ $indikator['description'] }}</small>
                                    </span>
                                    <span
                                        class="data-quality-item__count">{{ number_format($indikator['count'], 0, ',', '.') }}</span>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                        @empty
                            <p class="dashboard-quiet-state mb-0">Semua data prioritas sudah lengkap.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endcan

    </x-app-page>
@endsection
