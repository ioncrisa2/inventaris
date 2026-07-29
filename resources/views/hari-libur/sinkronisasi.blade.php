@extends('layouts.app')

@section('title', 'Sinkronisasi Hari Libur')

@section('content')
<x-app-page>
    <x-page-header title="Sinkronisasi Hari Libur" subtitle="Tarik data hari libur nasional dari API Nager.Date, bandingkan dengan data yang sudah ada, lalu pilih mana yang mau ditambahkan.">
        <x-slot:actions>
            <a href="{{ route('hari-libur.index') }}" class="btn btn-light">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-flash-alert />

    <div class="mb-4">
        <h2 class="h5 mb-3">Pilih Tahun</h2>
        <form method="GET" action="{{ route('hari-libur.sinkronisasi.create') }}" class="row g-3 align-items-end">
            <div class="col-auto">
                <label for="tahun" class="form-label">Tahun</label>
                <input
                    type="number"
                    name="tahun"
                    id="tahun"
                    class="form-control {{ $errors->has('tahun') ? 'is-invalid' : '' }}"
                    value="{{ $tahun ?? now()->year }}"
                    min="2000"
                    max="2100"
                    required
                >
                @error('tahun')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-download"></i>
                    Tampilkan Perbandingan
                </button>
            </div>
        </form>
    </div>

    @if($errorPesan)
        <div class="alert alert-danger mt-4">{{ $errorPesan }}</div>
    @endif

    @if($hasil)
        <x-data-table
            title="Akan Ditambahkan"
            subtitle="Tanggal dari API yang belum ada di data. Hilangkan centang kalau ada yang tidak ingin ditambahkan."
            class="mt-4"
        >
            @if(count($hasil['baru']))
                <form method="POST" action="{{ route('hari-libur.sinkronisasi.store') }}">
                    @csrf
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="table-col-width-40">
                                    <input type="checkbox" class="form-check-input" data-hari-libur-select-all aria-label="Pilih semua" checked>
                                </th>
                                <th class="table-col-width-150">Tanggal</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hasil['baru'] as $item)
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="pilihan[]"
                                        value="{{ $item['tanggal'] }}"
                                        data-hari-libur-pilihan
                                        checked
                                    >
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::parse($item['tanggal'])->translatedFormat('d F Y') }}</td>
                                <td>{{ $item['keterangan'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="d-flex justify-content-end mt-4 pt-4 border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i>
                            Terapkan Pilihan
                        </button>
                    </div>
                </form>
            @else
                <p class="text-body-secondary mb-0">Sudah sinkron, tidak ada tanggal baru untuk tahun {{ $tahun }}.</p>
            @endif
        </x-data-table>

        <x-data-table
            title="Sudah Ada di Data"
            subtitle="Tanggal ini sudah tercatat dan tidak akan diubah oleh sinkronisasi."
            class="mt-4"
        >
            @if(count($hasil['sudahAda']))
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="table-col-width-150">Tanggal</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil['sudahAda'] as $item)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($item['tanggal'])->translatedFormat('d F Y') }}</td>
                            <td>{{ $item['keterangan'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-body-secondary mb-0">Belum ada tanggal API tahun {{ $tahun }} yang cocok dengan data.</p>
            @endif
        </x-data-table>
    @endif
</x-app-page>
@endsection
