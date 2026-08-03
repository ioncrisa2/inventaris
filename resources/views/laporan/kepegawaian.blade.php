@extends('layouts.app')

@section('title', 'Laporan Kepegawaian - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        @php
            $showTenant = auth()->user()->isSuperAdmin();
            $kepegawaianActiveFilterCount = collect(request()->only([
                'koperasi_id',
                'unit_kerja_id',
                'status_karyawan',
            ]))->filter(fn ($value) => filled($value))->count();

            $kepegawaianSelectedUnit = request()->filled('unit_kerja_id')
                ? ($unitKerjas->firstWhere('id', (int) request('unit_kerja_id'))?->nama_unit ?? 'Unit tidak tersedia')
                : 'Semua unit kerja';
            $kepegawaianSelectedTenant = $selectedKoperasiId
                ? ($koperasis->firstWhere('id', $selectedKoperasiId)?->nama ?? 'Koperasi tidak tersedia')
                : 'Seluruh koperasi';
        @endphp

        <x-page-header title="Laporan Kepegawaian" subtitle="Sebaran tenaga kerja, status, dan gaji pokok.">
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <x-report-filter-button
                        target="kepegawaianReportFilter"
                        :active-count="$kepegawaianActiveFilterCount"
                    />
                    @can('laporan.kepegawaian.cetak')
                    <a class="btn btn-outline-primary" href="{{ route('laporan.kepegawaian.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    @endcan
                    @can('laporan.kepegawaian.export')
                    <a class="btn btn-outline-success" href="{{ route('laporan.kepegawaian.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-report-filter-summary
            :items="[
                ['label' => 'Koperasi', 'value' => $showTenant ? $kepegawaianSelectedTenant : auth()->user()->koperasi?->nama],
                ['label' => 'Unit', 'value' => $kepegawaianSelectedUnit],
                ['label' => 'Status', 'value' => request('status_karyawan') ?: 'Semua status'],
            ]"
        />

        <x-report-filter-modal
            id="kepegawaianReportFilter"
            title="Filter Laporan Kepegawaian"
            :action="route('laporan.kepegawaian')"
            :reset-url="route('laporan.kepegawaian')"
            :has-filters="$kepegawaianActiveFilterCount > 0"
            :has-errors="$errors->any()"
        >
            @if($showTenant)
                <div class="col-md-6">
                    <label class="form-label" for="kepegawaian_koperasi_id">Koperasi</label>
                    <select class="form-select @error('koperasi_id') is-invalid @enderror" id="kepegawaian_koperasi_id" name="koperasi_id">
                        <option value="">Seluruh koperasi</option>
                        @foreach($koperasis as $koperasi)
                            <option value="{{ $koperasi->id }}" @selected((string) old('koperasi_id', $selectedKoperasiId) === (string) $koperasi->id)>{{ $koperasi->nama }}</option>
                        @endforeach
                    </select>
                    @error('koperasi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="kepegawaian_unit_kerja_id">Unit Kerja</label>
                <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="kepegawaian_unit_kerja_id" name="unit_kerja_id">
                    <option value="">Semua unit kerja</option>
                    @foreach($unitKerjas as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_kerja_id', request('unit_kerja_id')) === (string) $unit->id)>
                            {{ $showTenant ? (($unit->koperasi?->nama ?? 'Tanpa koperasi').' — '.$unit->nama_unit) : $unit->nama_unit }}
                        </option>
                    @endforeach
                </select>
                @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="kepegawaian_status_karyawan">Status</label>
                <select class="form-select @error('status_karyawan') is-invalid @enderror" id="kepegawaian_status_karyawan" name="status_karyawan">
                    <option value="">Semua status</option>
                    @foreach(\App\Models\Karyawan::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status_karyawan', request('status_karyawan')) === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
                @error('status_karyawan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </x-report-filter-modal>

        <x-report-tabs
            label="Bagian laporan kepegawaian"
            :tabs="[
                'kepegawaian-ringkasan' => [
                    'label' => 'Ringkasan',
                    'description' => 'Ikhtisar tenaga kerja aktif.',
                    'icon' => 'bi-grid-1x2',
                ],
                'kepegawaian-status' => [
                    'label' => 'Rekap Status',
                    'description' => 'Jumlah karyawan per status.',
                    'icon' => 'bi-person-badge',
                ],
                'kepegawaian-unit' => [
                    'label' => 'Distribusi Unit',
                    'description' => 'Sebaran karyawan per unit.',
                    'icon' => 'bi-diagram-3',
                ],
                'kepegawaian-detail' => [
                    'label' => 'Detail Karyawan',
                    'description' => 'Daftar karyawan sesuai filter.',
                    'icon' => 'bi-table',
                ],
            ]"
        >
            <x-report-tab-pane
                id="kepegawaian-ringkasan"
                title="Ringkasan Kepegawaian"
                description="Informasi utama tenaga kerja berdasarkan filter yang sedang diterapkan."
                active
            >
        <div class="report-stat-grid">
            <x-stat-card icon="bi-people" label="Total Karyawan" :value="number_format($totalKaryawan, 0, ',', '.')" plain />
            <x-stat-card icon="bi-person-check" label="Karyawan Aktif" :value="number_format($totalAktif, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Gaji Pokok Karyawan Aktif" :value="'Rp '.number_format($totalGajiAktif, 0, ',', '.')" compact plain />
        </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="kepegawaian-status"
                title="Rekapitulasi Status"
                description="Jumlah karyawan untuk setiap status hubungan kerja."
            >
                    <div class="table-responsive report-tab-table">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Status</th><th class="text-end">Jumlah</th></tr>
                            </thead>
                            <tbody>
                                @forelse($rekapStatus as $rekap)
                                    <tr>
                                        <td>{{ $rekap->status_karyawan }}</td>
                                        <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <x-empty-row :colspan="2">Tidak ada data status.</x-empty-row>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr><td>Total</td><td class="text-end">{{ number_format($totalKaryawan, 0, ',', '.') }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="kepegawaian-unit"
                title="Distribusi per Unit Kerja"
                description="Sebaran jumlah karyawan pada masing-masing unit kerja."
            >
                    <div class="table-responsive report-tab-table">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Unit Kerja</th><th class="text-end">Jumlah Karyawan</th></tr>
                            </thead>
                            <tbody>
                                @forelse($rekapUnitKerja as $rekap)
                                    <tr>
                                        <td>{{ $showTenant ? (($rekap->unitKerja?->koperasi?->nama ?? 'Tanpa koperasi').' — '.($rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja')) : ($rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja') }}</td>
                                        <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <x-empty-row :colspan="2">Tidak ada data unit kerja.</x-empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="kepegawaian-detail"
                title="Detail Karyawan"
                description="Daftar identitas kerja dan gaji pokok karyawan yang cocok dengan filter."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NIK</th>
                            <th>Nama Lengkap</th>
                            @if($showTenant)
                                <th>Koperasi</th>
                            @endif
                            <th>Unit Kerja</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                            <th class="text-end">Gaji Pokok</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawans as $karyawan)
                            <tr>
                                <td><strong>{{ $karyawan->nik }}</strong></td>
                                <td>{{ $karyawan->nama_lengkap }}</td>
                                @if($showTenant)
                                    <td>{{ $karyawan->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                                @endif
                                <td>{{ $karyawan->unitKerja?->nama_unit ?? '—' }}</td>
                                <td>{{ $karyawan->jabatan }}</td>
                                <td><x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge></td>
                                <td class="text-end">Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="$showTenant ? 7 : 6">Tidak ada karyawan yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$karyawans" class="d-print-none" />
            </x-report-tab-pane>
        </x-report-tabs>

</x-app-page>
@endsection
