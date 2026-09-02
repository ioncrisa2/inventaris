@extends('layouts.app')

@section('title', 'Komponen Gaji')

@php
    $showTenant = auth()->user()->isSuperAdmin();
    $canUpdate = auth()->user()->can('komponen-gaji.update');
    $canDelete = auth()->user()->can('komponen-gaji.delete');
    $showActions = $canUpdate || $canDelete;
@endphp

@section('content')
<x-app-page long-footer>
        <x-page-header title="Komponen Gaji" subtitle="Aturan tunjangan dan potongan untuk perhitungan gaji.">
            <x-slot:actions>
                @can('komponen-gaji.create')
                    <a class="btn btn-primary" href="{{ route('komponen-gaji.create') }}">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Komponen Gaji
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$komponenGaji">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('komponen-gaji.index')"
                    :reset-route="route('komponen-gaji.index')"
                    :has-filters="request()->hasAny(['jenis', 'search', 'koperasi_id'])"
                >
                    <div class="col-12 col-sm-auto">
                    <select name="jenis" class="form-select" data-submit-on-change>
                        <option value="">Semua Jenis</option>
                        @foreach(['Tunjangan', 'Potongan'] as $jenis)
                            <option value="{{ $jenis }}" @selected(request('jenis') === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                    </div>
                    <x-tenant-filter :koperasis="$koperasis" :selected="$selectedKoperasiId" id="komponen_gaji_koperasi_id" />
                    <div class="col-12 col-sm-auto filter-form__search">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Cari nama komponen...">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('komponen-gaji.delete')
                    <x-bulk-action-bar
                        id="komponen-gaji"
                        noun="komponen gaji"
                        :delete-action="route('komponen-gaji.bulk-destroy')"
                        delete-message="Komponen hanya akan dihapus jika belum digunakan pada transaksi gaji." />
                @endcan
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @can('komponen-gaji.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="komponen-gaji" label="Pilih semua komponen gaji di halaman ini" select-all />
                            </th>
                            @endcan
                            <th>Nama Komponen</th>
                            @if($showTenant)
                                <th>Koperasi</th>
                            @endif
                            <th class="table-col-width-120">Jenis</th>
                            <th>Metode Perhitungan</th>
                            <th class="text-end table-col-width-150">Nilai Default</th>
                            @if($showActions)
                                <th class="table-col-width-100">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($komponenGaji as $data)
                        <tr>
                            @can('komponen-gaji.delete')
                            <td class="selection-column">
                                <x-table-checkbox group="komponen-gaji" :value="$data->id" :label="'Pilih '.$data->nama_komponen" />
                            </td>
                            @endcan
                            <td><strong>{{ $data->nama_komponen }}</strong></td>
                            @if($showTenant)
                                <td>{{ $data->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                            @endif
                            <td>
                                <x-badge :color="$data->jenis === 'Tunjangan' ? 'text-bg-success' : 'text-bg-secondary'">{{ $data->jenis }}</x-badge>
                            </td>
                            <td>
                                {{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$data->metode_perhitungan] ?? $data->metode_perhitungan }}
                                @if(in_array($data->metode_perhitungan, ['persentase', 'persentase_pengali'], true))
                                    <span class="text-body-secondary small">(dasar: gaji pokok)</span>
                                @elseif($data->metode_perhitungan === 'per_hari')
                                    <span class="text-body-secondary small">(dikali jumlah absensi Hadir dalam range tanggal yang diinput saat transaksi)</span>
                                @elseif($data->metode_perhitungan === 'harian_manual')
                                    <span class="text-body-secondary small">(dikali jumlah hari yang diketik manual saat transaksi dibuat)</span>
                                @elseif($data->metode_perhitungan === 'nominal_tidak_tetap')
                                    <span class="text-body-secondary small">(nominal diisi saat transaksi dibuat)</span>
                                @elseif($data->metode_perhitungan === 'nominal_tetap_list')
                                    <span class="text-body-secondary small">(keterangan dan nominal diisi sebagai daftar saat transaksi dibuat)</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(in_array($data->metode_perhitungan, \App\Models\KomponenGaji::METODE_INPUT_TRANSAKSI, true))
                                    <span class="text-body-secondary">Diisi saat transaksi</span>
                                @elseif(in_array($data->metode_perhitungan, ['persentase', 'persentase_pengali'], true))
                                    {{ rtrim(rtrim($data->nilai_default, '0'), '.') }}%
                                @elseif(in_array($data->metode_perhitungan, ['per_hari', 'harian_manual'], true))
                                    Rp {{ number_format($data->nilai_default, 0, ',', '.') }} /hari
                                @else
                                    Rp {{ number_format($data->nilai_default, 0, ',', '.') }}
                                @endif
                            </td>
                            @if($showActions)
                            <td>
                                <div class="table-actions">
                                    @can('update', $data)
                                    <a
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        title="Edit"
                                        aria-label="Edit {{ $data->nama_komponen }}"
                                        href="{{ route('komponen-gaji.edit', $data) }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan

                                    @can('delete', $data)
                                    <x-delete-button
                                        :url="route('komponen-gaji.destroy', $data->id)"
                                        :message="'Hapus komponen gaji &quot;'.$data->nama_komponen.'&quot;? Penghapusan akan ditolak jika komponen sudah dipakai pada transaksi gaji.'"
                                        :label="'Hapus '.$data->nama_komponen"
                                    />
                                    @endcan
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <x-empty-row :colspan="4 + ($showTenant ? 1 : 0) + ($canDelete ? 1 : 0) + ($showActions ? 1 : 0)">
                            @if(request()->hasAny(['jenis', 'search', 'koperasi_id']))
                                Tidak ada komponen gaji yang cocok dengan filter.
                                <a href="{{ route('komponen-gaji.index') }}">Hapus filter</a>.
                            @else
                                Data komponen gaji belum tersedia.
                            @endif
                        </x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
