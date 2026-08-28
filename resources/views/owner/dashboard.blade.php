@extends('layouts.app')

@section('title', 'Ringkasan Platform - System Owner')

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Ringkasan Platform"
            subtitle="Kondisi operasional seluruh koperasi dalam bentuk agregat, tanpa membuka data individual.">
            <x-slot:actions>
                <span class="owner-updated-at">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    Diperbarui
                    {{ \Carbon\CarbonImmutable::parse($analytics['diperbaruiPada'])->locale('id')->diffForHumans() }}
                </span>
            </x-slot:actions>
        </x-page-header>

        <section class="owner-command-bar mb-4" aria-label="Filter ringkasan platform">
            @include('owner.partials.analytics-filter', [
                'action' => route('owner.dashboard'),
                'filters' => $filters,
                'showKoperasi' => false,
                'showModule' => false,
            ])
        </section>

        @include('owner.partials.metric-cards', ['cards' => $analytics['kartu']])

        <div class="owner-dashboard-grid mt-4">
            <div class="owner-dashboard-grid__main">
                @include('owner.partials.chart', [
                    'id' => 'ownerGrowthChart',
                    'title' => 'Pertumbuhan platform',
                    'subtitle' => 'Penambahan data baru per bulan pada rentang terpilih.',
                    'chart' => $analytics['grafik']['pertumbuhan'],
                ])
            </div>

            <aside class="owner-platform-pulse" aria-labelledby="platformPulseTitle">
                <div>
                    <span class="owner-platform-pulse__mark" aria-hidden="true"><i class="bi bi-broadcast-pin"></i></span>
                    <h2 id="platformPulseTitle">Postur platform</h2>
                    <p>Snapshot status koperasi pada akhir periode.</p>
                </div>
                @php($koperasiStatus = $analytics['platform']['koperasi'])
                <dl>
                    <div>
                        <dt>Aktif</dt>
                        <dd>{{ number_format($koperasiStatus['aktif'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Segera berakhir</dt>
                        <dd>{{ number_format($koperasiStatus['akan_kedaluwarsa'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Kedaluwarsa</dt>
                        <dd>{{ number_format($koperasiStatus['kedaluwarsa'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Dinonaktifkan</dt>
                        <dd>{{ number_format($koperasiStatus['nonaktif'], 0, ',', '.') }}</dd>
                    </div>
                </dl>
                @if(Route::has('owner.system-health') && Route::has('owner.storage'))
                    <div class="owner-platform-pulse__actions">
                        <a class="btn btn-light" href="{{ route('owner.system-health') }}">Periksa kesehatan</a>
                        <a class="btn btn-outline-light" href="{{ route('owner.storage') }}">Lihat storage</a>
                    </div>
                @endif
            </aside>
        </div>

        <section class="owner-panel mt-4" aria-labelledby="comparisonTitle">
            <header class="owner-panel__header">
                <div>
                    <h2 id="comparisonTitle">Perbandingan koperasi</h2>
                    <p>Ringkasan terukur untuk menentukan area yang perlu diperhatikan.</p>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('owner.analytics', $filters) }}">Buka analitik
                    lengkap</a>
            </header>
            <div class="table-responsive">
                <table class="table align-middle owner-comparison-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Koperasi</th>
                            <th scope="col" class="text-end">Barang</th>
                            <th scope="col" class="text-end">Nilai inventaris</th>
                            <th scope="col" class="text-end">Karyawan aktif</th>
                            <th scope="col" class="text-end">Absensi</th>
                            <th scope="col"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($analytics['perbandinganKoperasi'] as $row)
                            <tr>
                                <th scope="row">{{ $row['nama'] }}</th>
                                <td class="text-end">{{ number_format($row['total_barang'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ \App\Support\OwnerMetricFormatter::rupiah($row['nilai_inventaris']) }}
                                </td>
                                <td class="text-end">{{ number_format($row['karyawan_aktif'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row['total_absensi'], 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-link"
                                        href="{{ route('owner.analytics.koperasi', [
                                            'koperasi' => $row['id'],
                                            'tanggal_awal' => $filters['tanggal_awal'],
                                            'tanggal_akhir' => $filters['tanggal_akhir'],
                                        ]) }}">
                                        Ringkasan <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-body-secondary py-5">Belum ada koperasi untuk
                                    dibandingkan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">@include('owner.partials.privacy-note')</div>
    </x-app-page>
@endsection
