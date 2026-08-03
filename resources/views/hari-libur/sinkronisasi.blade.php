@extends('layouts.app')

@section('title', 'Sinkronisasi Hari Libur')

@section('content')
    <x-app-page long-footer>
        <x-page-header
            title="Sinkronisasi Hari Libur"
            subtitle="Ambil data publik, periksa hasilnya, lalu tambahkan tanggal terpilih ke satu koperasi."
        >
            <x-slot:actions>
                <a
                    href="{{ $tahun && $koperasiId
                        ? route('hari-libur.koperasi', ['tahun' => $tahun, 'koperasi' => $koperasiId])
                        : route('hari-libur.index') }}"
                    class="btn btn-light"
                >
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="settings-callout mb-4">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <div>
                <strong>Periksa tanggal sebelum menerapkan.</strong>
                <p class="mb-0">
                    Data berasal dari API publik pihak ketiga dan bukan sumber hukum resmi. Data manual yang sudah ada
                    tidak akan diubah. Sinkronisasi hanya menambahkan tanggal baru ke koperasi tujuan.
                </p>
            </div>
        </div>

        <x-section-card
            title="Ambil Data Publik"
            subtitle="Pilih tahun dan koperasi primer yang akan menerima data."
        >
            <form method="GET" action="{{ route('hari-libur.sinkronisasi.create') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="tahun" class="form-label">Tahun <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        name="tahun"
                        id="tahun"
                        class="form-control @error('tahun') is-invalid @enderror"
                        value="{{ old('tahun', $tahun ?? now()->year) }}"
                        min="2000"
                        max="2100"
                        required
                    >
                    @error('tahun')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-5 col-xl-4">
                    <label for="koperasi_id" class="form-label">Koperasi Tujuan <span class="text-danger">*</span></label>
                    <select name="koperasi_id" id="koperasi_id" class="form-select @error('koperasi_id') is-invalid @enderror" required>
                        <option value="">Pilih koperasi primer</option>
                        @foreach($koperasis as $opsiKoperasi)
                            <option value="{{ $opsiKoperasi->id }}" @selected((string) old('koperasi_id', $koperasiId) === (string) $opsiKoperasi->id)>
                                {{ $opsiKoperasi->nama }}
                            </option>
                        @endforeach
                    </select>
                    @error('koperasi_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-cloud-download"></i>
                        Tampilkan Pratinjau
                    </button>
                </div>
            </form>
        </x-section-card>

        @if($errorPesan)
            <div class="alert alert-danger d-flex gap-2 align-items-start mt-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <div>
                    <strong>Pratinjau tidak dapat dimuat.</strong>
                    <div>{{ $errorPesan }}</div>
                </div>
            </div>
        @endif

        @if($hasil && $koperasi)
            <x-data-table
                title="Tanggal Baru"
                subtitle="{{ count($hasil['baru']) }} tanggal belum tercatat di {{ $koperasi->nama }}. Centang hanya data yang sudah diperiksa."
                class="mt-4"
            >
                @if(count($hasil['baru']))
                    <form id="sinkronisasi-hari-libur-form" method="POST" action="{{ route('hari-libur.sinkronisasi.store') }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">
                        <input type="hidden" name="koperasi_id" value="{{ $koperasi->id }}">
                        <input type="hidden" name="snapshot" value="{{ $hasil['snapshot'] }}">
                    </form>

                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="table-col-width-40 selection-column">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        data-hari-libur-select-all
                                        aria-label="Pilih semua tanggal baru"
                                    >
                                </th>
                                <th class="table-col-width-150">Tanggal</th>
                                <th class="table-col-width-150">Jenis</th>
                                <th>Keterangan dari API</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hasil['baru'] as $item)
                                <tr>
                                    <td class="selection-column">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            form="sinkronisasi-hari-libur-form"
                                            name="pilihan[]"
                                            value="{{ $item['tanggal'] }}"
                                            data-hari-libur-pilihan
                                            aria-label="Pilih {{ $item['keterangan'] }}"
                                        >
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($item['tanggal'])->translatedFormat('d M Y') }}</td>
                                    <td>
                                        @if($item['jenis'] === 'leave')
                                            <span class="badge text-bg-warning">Cuti bersama</span>
                                        @else
                                            <span class="badge text-bg-success">Libur nasional</span>
                                        @endif
                                    </td>
                                    <td>{{ $item['keterangan'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 p-3 border-top">
                        <small class="text-body-secondary">
                            Tujuan: <strong>{{ $koperasi->nama }}</strong>. API akan diperiksa ulang saat diterapkan.
                        </small>
                        <button type="submit" form="sinkronisasi-hari-libur-form" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i>
                            Terapkan Tanggal Terpilih
                        </button>
                    </div>
                @else
                    <div class="p-4 text-center text-body-secondary">
                        <i class="bi bi-check-circle d-block fs-3 mb-2" aria-hidden="true"></i>
                        Semua tanggal API tahun {{ $tahun }} sudah tercatat di {{ $koperasi->nama }}.
                    </div>
                @endif
            </x-data-table>

            <x-data-table
                title="Sudah Tercatat"
                subtitle="{{ count($hasil['sudahAda']) }} tanggal sudah ada di {{ $koperasi->nama }} dan tidak akan diubah."
                class="mt-4"
            >
                @if(count($hasil['sudahAda']))
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="table-col-width-150">Tanggal</th>
                                <th class="table-col-width-150">Jenis API</th>
                                <th>Keterangan dari API</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hasil['sudahAda'] as $item)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($item['tanggal'])->translatedFormat('d M Y') }}</td>
                                    <td>{{ $item['jenis'] === 'leave' ? 'Cuti bersama' : 'Libur nasional' }}</td>
                                    <td>{{ $item['keterangan'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-4 text-center text-body-secondary">
                        Belum ada tanggal API tahun {{ $tahun }} yang tercatat di {{ $koperasi->nama }}.
                    </div>
                @endif
            </x-data-table>
        @endif
    </x-app-page>
@endsection
