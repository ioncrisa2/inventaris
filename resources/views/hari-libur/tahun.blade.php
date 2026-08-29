@extends('layouts.app')

@section('title', 'Hari Libur '.$tahun)

@php
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $canUpdate = auth()->user()->can('hari-libur.update');
    $canDelete = auth()->user()->can('hari-libur.delete');
    $showActions = $canUpdate || $canDelete;
    $detailRoute = route('hari-libur.tahun', ['tahun' => $tahun]);
@endphp

@section('content')
    <x-app-page long-footer>
        <x-page-header
            title="Hari Libur {{ $tahun }}"
            :subtitle="$isSuperAdmin
                ? 'Baseline nasional yang otomatis berlaku untuk seluruh koperasi primer.'
                : 'Gabungan baseline nasional dan hari libur tambahan milik '.($koperasi?->nama ?? 'koperasi Anda').'.'"
        >
            <x-slot:actions>
                <a
                    href="{{ route('hari-libur.index') }}"
                    class="btn btn-light"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>

                @if($isSuperAdmin)
                    <a
                        href="{{ route('hari-libur.sinkronisasi.create', [
                            'tahun' => $tahun,
                        ]) }}"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-cloud-download"></i>
                        Sinkronkan API
                    </a>
                @endif

                @can('hari-libur.create')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createHariLiburModal">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Hari Libur
                    </button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$hariLibur">
            <x-slot:toolbar>
                <x-filter-form
                    :action="$detailRoute"
                    :reset-route="$detailRoute"
                    :has-filters="request()->filled('search')"
                    submit-label="Cari"
                    submit-icon="bi-search"
                >
                    <div class="col-12 col-sm-auto">
                        <input
                            type="search"
                            name="search"
                            class="form-control"
                            value="{{ request('search') }}"
                            placeholder="Cari keterangan…"
                            aria-label="Cari keterangan hari libur"
                        >
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('hari-libur.delete')
                    <x-bulk-action-bar
                        id="hari-libur"
                        noun="hari libur"
                        :delete-action="route('hari-libur.bulk-destroy')"
                        delete-message="Hari libur terpilih akan dihapus permanen."
                    />
                @endcan
            </x-slot:bulkActions>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @can('hari-libur.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="hari-libur" label="Pilih semua hari libur di halaman ini" select-all />
                            </th>
                        @endcan
                        <th class="table-col-width-180">Tanggal</th>
                        <th class="table-col-width-150">Sumber</th>
                        <th>Keterangan</th>
                        @if($showActions)
                            <th class="table-col-width-100">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($hariLibur as $data)
                        <tr>
                            @can('hari-libur.delete')
                                <td class="selection-column">
                                    @can('delete', $data)
                                        <x-table-checkbox group="hari-libur" :value="$data->id" :label="'Pilih '.$data->keterangan" />
                                    @endcan
                                </td>
                            @endcan
                            <td><strong>{{ $data->tanggal->translatedFormat('d F Y') }}</strong></td>
                            <td>
                                @if($data->isBaselineNasional())
                                    <span class="badge text-bg-primary">Baseline nasional</span>
                                @else
                                    <span class="badge text-bg-warning">Tambahan primer</span>
                                @endif
                            </td>
                            <td>{{ $data->keterangan }}</td>
                            @if($showActions)
                                <td>
                                    <div class="table-actions">
                                        @can('update', $data)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-action btn-action-neutral"
                                                title="Edit"
                                                aria-label="Edit {{ $data->keterangan }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editHariLiburModal"
                                                data-edit-url="{{ route('hari-libur.update', $data->id) }}"
                                                data-id="{{ $data->id }}"
                                                data-tanggal="{{ $data->tanggal->format('Y-m-d') }}"
                                                data-keterangan="{{ $data->keterangan }}"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endcan

                                        @can('delete', $data)
                                            <x-delete-button
                                                :url="route('hari-libur.destroy', $data->id)"
                                                :message="'Hapus hari libur &quot;'.$data->keterangan.'&quot;? Tindakan ini tidak dapat dibatalkan.'"
                                                :label="'Hapus '.$data->keterangan"
                                            />
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty-row :colspan="3 + ($canDelete ? 1 : 0) + ($showActions ? 1 : 0)">
                            @if(request()->filled('search'))
                                Tidak ada hari libur yang cocok dengan pencarian.
                                <a href="{{ $detailRoute }}">Hapus pencarian</a>.
                            @else
                                @if($isSuperAdmin)
                                    Belum ada baseline hari libur nasional untuk tahun {{ $tahun }}.
                                    <x-slot:action>
                                        <a
                                            href="{{ route('hari-libur.sinkronisasi.create', [
                                                'tahun' => $tahun,
                                            ]) }}"
                                            class="btn btn-primary btn-sm"
                                        >
                                            <i class="bi bi-cloud-download"></i>
                                            Sinkronkan API
                                        </a>
                                    </x-slot:action>
                                @else
                                    Belum ada hari libur untuk tahun {{ $tahun }}.
                                    @can('hari-libur.create')
                                        <x-slot:action>
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#createHariLiburModal"
                                            >
                                                <i class="bi bi-plus-circle"></i>
                                                Tambah Hari Libur
                                            </button>
                                        </x-slot:action>
                                    @endcan
                                @endif
                            @endif
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>

    @include('hari-libur._modals')
@endsection
