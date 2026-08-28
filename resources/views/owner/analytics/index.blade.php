@extends('layouts.app')

@section('title', 'Analitik Koperasi - System Owner')

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Analitik Koperasi"
            subtitle="Bandingkan kecenderungan operasional tanpa masuk ke record individual." />

        <section class="owner-command-bar mb-4">
            @include('owner.partials.analytics-filter', [
                'action' => route('owner.analytics'),
                'filters' => $filters,
                'koperasis' => $analytics['pilihanKoperasi'],
            ])
        </section>

        @include('owner.partials.metric-cards', ['cards' => $analytics['kartu']])

        <div class="mt-4">
            @include('owner.partials.chart', [
                'id' => 'ownerGlobalGrowthChart',
                'title' => 'Pertumbuhan lintas koperasi',
                'subtitle' => 'Jumlah record baru per bulan, bukan aktivitas individual.',
                'chart' => $analytics['grafik']['pertumbuhan'],
            ])
        </div>

        <section class="owner-panel mt-4" aria-labelledby="ownerComparisonFullTitle">
            <header class="owner-panel__header">
                <div>
                    <h2 id="ownerComparisonFullTitle">Ringkasan per koperasi</h2>
                    <p>Pilih satu baris untuk membuka grafik agregat koperasi tersebut.</p>
                </div>
            </header>
            <div class="table-responsive">
                <table class="table align-middle owner-comparison-table mb-0">
                    <thead>
                        <tr>
                            <th>Koperasi</th>
                            <th class="text-end">Barang</th>
                            <th class="text-end">Nilai aset</th>
                            <th class="text-end">Karyawan</th>
                            <th class="text-end">Gaji bersih</th>
                            <th></th>
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
                                <td class="text-end">{{ \App\Support\OwnerMetricFormatter::rupiah($row['total_gaji_bersih']) }}
                                </td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('owner.analytics.koperasi', ['koperasi' => $row['id'], ...$filters]) }}">Buka</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-body-secondary py-5">Belum ada data koperasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">@include('owner.partials.privacy-note')</div>
    </x-app-page>
@endsection
