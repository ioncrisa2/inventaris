@extends('layouts.app')

@section('title', 'Karyawan - Sistem Inventaris & Kepegawaian')

@php
    $dapatMelihatGaji = auth()->user()->can('karyawan.gaji.view')
        || auth()->user()->can('karyawan.gaji.update');
    $jumlahKolom = 6
        + (auth()->user()->can('karyawan.delete') ? 1 : 0)
        + ($dapatMelihatGaji ? 1 : 0);
@endphp

@section('content')
<x-app-page>
        <x-page-header title="Karyawan">
            <x-slot:actions>
                @can('karyawan.create')
                    <a class="btn btn-primary" href="{{ route('karyawan.create') }}">
                        <i class="bi bi-person-plus"></i>
                        Tambah Karyawan
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$karyawan">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('karyawan.index')"
                    :reset-route="route('karyawan.index')"
                    :has-filters="request()->hasAny(['search', 'unit_kerja_id', 'status_karyawan', 'kelengkapan'])"
                >
                    <div class="col-12 col-lg-auto">
                        <label class="visually-hidden" for="search">Cari karyawan</label>
                        <input
                            class="form-control"
                            id="search"
                            name="search"
                            type="search"
                            value="{{ request('search') }}"
                            placeholder="Cari NIK, nama, jabatan…"
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="unit_kerja_id">Unit kerja</label>
                        <select class="form-select" id="unit_kerja_id" name="unit_kerja_id">
                            <option value="">Semua unit kerja</option>
                            @foreach($unitKerjas as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>
                                    {{ $unit->nama_unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="status_karyawan">Status</label>
                        <select class="form-select" id="status_karyawan" name="status_karyawan">
                            <option value="">Semua status</option>
                            @foreach(\App\Models\Karyawan::STATUSES as $status)
                                <option value="{{ $status }}" @selected(request('status_karyawan') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="kelengkapan">Kelengkapan data</label>
                        <select class="form-select" id="kelengkapan" name="kelengkapan">
                            <option value="">Semua kelengkapan</option>
                            <option value="data-inti" @selected(request('kelengkapan') === 'data-inti')>Data inti belum lengkap</option>
                        </select>
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('karyawan.delete')
                    <x-bulk-action-bar
                        id="karyawan"
                        noun="karyawan"
                        :delete-action="route('karyawan.bulk-destroy')"
                        delete-message="Karyawan hanya dapat dihapus jika belum memiliki absensi, transaksi gaji, histori perubahan, atau bawahan langsung." />
                @endcan
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @can('karyawan.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="karyawan" label="Pilih semua karyawan di halaman ini" select-all />
                            </th>
                            @endcan
                            <th class="table-col-width-120">NIK</th>
                            <th>Nama Lengkap</th>
                            <th>Unit Kerja</th>
                            <th>Jabatan</th>
                            <th class="table-col-width-120">Status</th>
                            @if($dapatMelihatGaji)
                                <th class="text-end table-col-width-150">Gaji Pokok</th>
                            @endif
                            <th class="text-nowrap table-col-width-160">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawan as $data)
                            <tr>
                                @can('karyawan.delete')
                                <td class="selection-column">
                                    <x-table-checkbox group="karyawan" :value="$data->id" :label="'Pilih '.$data->nama_lengkap" />
                                </td>
                                @endcan
                                <td><strong>{{ $data->nik }}</strong></td>
                                <td>{{ $data->nama_lengkap }}</td>
                                <td>{{ $data->unitKerja?->nama_unit ?? 'Belum ditentukan' }}</td>
                                <td>{{ $data->jabatan }}</td>
                                <td><x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$data->status_karyawan] ?? 'bg-secondary'">{{ $data->status_karyawan }}</x-badge></td>
                                @if($dapatMelihatGaji)
                                    <td class="text-end">Rp {{ number_format($data->gaji_pokok, 0, ',', '.') }}</td>
                                @endif
                                <td class="text-nowrap">
                                    <div class="table-actions">
                                        <a
                                            class="btn btn-sm btn-action btn-action-neutral"
                                            href="{{ route('karyawan.show', $data) }}"
                                            aria-label="Detail {{ $data->nama_lengkap }}"
                                            title="Detail"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('delete', $data)
                                            <x-delete-button
                                                :url="route('karyawan.destroy', $data)"
                                                :message="'Hapus karyawan &quot;'.$data->nama_lengkap.'&quot;? Penghapusan akan ditolak jika masih memiliki absensi, transaksi gaji, histori perubahan, atau bawahan langsung.'"
                                                :label="'Hapus '.$data->nama_lengkap"
                                            />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="$jumlahKolom">
                                @if(request()->hasAny(['search', 'unit_kerja_id', 'status_karyawan', 'kelengkapan']))
                                    Tidak ada karyawan yang cocok dengan filter.
                                    <a href="{{ route('karyawan.index') }}">Hapus filter</a>.
                                @else
                                    Data karyawan belum tersedia.
                                    <a href="{{ route('karyawan.create') }}">Tambah karyawan pertama</a>.
                                @endif
                            </x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table>
</x-app-page>
@endsection
