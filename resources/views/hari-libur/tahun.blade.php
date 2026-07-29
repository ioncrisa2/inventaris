@extends('layouts.app')

@section('title', 'Hari Libur '.$tahun)

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Hari Libur {{ $tahun }}" subtitle="Semua tanggal libur nasional pada tahun {{ $tahun }}.">
            <x-slot:actions>
                <a href="{{ route('hari-libur.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createHariLiburModal">
                    <i class="bi bi-plus-circle"></i>
                    Tambah Hari Libur
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$hariLibur">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('hari-libur.tahun', ['tahun' => $tahun])"
                    :reset-route="route('hari-libur.tahun', ['tahun' => $tahun])"
                    :has-filters="request()->hasAny(['search'])"
                    submit-label="Cari"
                    submit-icon="bi-search"
                >
                    <div class="col-12 col-sm-auto">
                        <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                            placeholder="Cari keterangan...">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('hari-libur.delete')
                    <x-bulk-action-bar id="hari-libur" noun="hari libur" :delete-action="route('hari-libur.bulk-destroy')"
                        delete-message="Hari libur terpilih akan dihapus permanen." />
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
                        <th class="table-col-width-150">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="table-col-width-100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hariLibur as $data)
                        <tr>
                            @can('hari-libur.delete')
                                <td class="selection-column">
                                    <x-table-checkbox group="hari-libur" :value="$data->id" :label="'Pilih '.$data->keterangan" />
                                </td>
                            @endcan
                            <td><strong>{{ $data->tanggal->translatedFormat('d F Y') }}</strong></td>
                            <td>{{ $data->keterangan }}</td>
                            <td>
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-action btn-action-neutral" title="Edit"
                                        aria-label="Edit {{ $data->keterangan }}"
                                        data-bs-toggle="modal" data-bs-target="#editHariLiburModal"
                                        data-edit-url="{{ route('hari-libur.update', $data->id) }}"
                                        data-id="{{ $data->id }}"
                                        data-tanggal="{{ $data->tanggal->format('Y-m-d') }}"
                                        data-keterangan="{{ $data->keterangan }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <x-delete-button :url="route('hari-libur.destroy', $data->id)"
                                        :message="'Hapus hari libur &quot;'.$data->keterangan.'&quot;? Tindakan ini tidak dapat dibatalkan.'"
                                        :label="'Hapus '.$data->keterangan" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row :colspan="auth()->user()->can('hari-libur.delete') ? 4 : 3">Belum ada hari libur untuk tahun {{ $tahun }}.</x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>

    @include('hari-libur._modals')
@endsection
