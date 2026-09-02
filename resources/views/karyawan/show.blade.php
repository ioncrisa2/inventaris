@extends('layouts.app')

@section('title', 'Detail Karyawan - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header
            title="{{ $karyawan->nama_lengkap }}"
            subtitle="{{ $karyawan->nik }} — {{ $karyawan->jabatan }} — {{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}{{ auth()->user()->isSuperAdmin() ? ' — '.($karyawan->koperasi?->nama ?? 'Tanpa koperasi') : '' }}"
        >
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-success" href="{{ route('absensi.show', $karyawan) }}">
                        <i class="bi bi-calendar-check"></i>
                        Lihat Absensi
                    </a>
                    @if($jenisPerubahanTersedia !== [])
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#employeeChangeModal">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            Edit Data Karyawan
                        </button>
                    @endif
                    <a class="btn btn-light" href="{{ route('karyawan.index') }}">Kembali</a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        @can('manageAccount', $karyawan)
            <section class="card mb-4" aria-labelledby="akun-login-title">
                <div class="card-header">
                    <h2 class="h6 mb-0" id="akun-login-title">Akun Login Karyawan</h2>
                </div>
                <div class="card-body">
                    @if($karyawan->user)
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div>
                                <strong class="d-block">{{ $karyawan->user->name }}</strong>
                                <span class="text-body-secondary">{{ $karyawan->user->email }}</span>
                                <div class="small text-body-secondary mt-1">
                                    Role: {{ $karyawan->user->roles->map->displayName()->implode(', ') ?: 'Belum memiliki role' }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('karyawan.akun.destroy', $karyawan) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger" type="submit">
                                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                                    Lepas Hubungan Akun
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="text-body-secondary mb-3">
                            Hubungkan data karyawan ini dengan akun login dari koperasi yang sama agar fitur personal dapat digunakan.
                        </p>
                        <form class="row g-3 align-items-end" method="POST" action="{{ route('karyawan.akun.update', $karyawan) }}">
                            @csrf
                            @method('PUT')
                            <div class="col-lg-8">
                                <label class="form-label" for="user_id">Akun login <span class="text-danger">*</span></label>
                                <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                                    <option value="">Pilih akun yang belum terhubung</option>
                                    @foreach($availableAccountUsers as $accountUser)
                                        <option value="{{ $accountUser->id }}" @selected((int) old('user_id') === (int) $accountUser->id)>
                                            {{ $accountUser->name }} — {{ $accountUser->email }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($availableAccountUsers->isEmpty())
                                    <div class="form-text">Belum ada akun koperasi yang tersedia untuk dihubungkan.</div>
                                @endif
                            </div>
                            <div class="col-lg-4">
                                <button class="btn btn-primary w-100" type="submit" @disabled($availableAccountUsers->isEmpty())>
                                    <i class="bi bi-link" aria-hidden="true"></i>
                                    Hubungkan Akun
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endcan

        <div class="card employee-detail-card">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <span>{{ $karyawan->nama_lengkap }}</span>
                <div class="d-flex align-items-center gap-2">
                    @if($karyawan->tanggal_mengundurkan_diri)
                    <span class="text-body-secondary small">Keluar: {{ $karyawan->tanggal_mengundurkan_diri->translatedFormat('d F Y') }}</span>
                    @endif
                    <x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4 employee-detail-layout">
                    <aside class="col-lg-4 col-xl-3">
                        <div class="employee-detail-sidebar">
                            <div class="employee-detail-profile">
                                <x-image-preview
                                    :src="$karyawan->foto_karyawan ? \Illuminate\Support\Facades\Storage::url($karyawan->foto_karyawan) : null"
                                    alt="Foto {{ $karyawan->nama_lengkap }}"
                                    icon="bi-person"
                                    size="avatar"
                                />
                                @php($fotoKaryawanRegistry = $karyawan->storedFiles->firstWhere('collection', 'foto_karyawan'))
                                @if($fotoKaryawanRegistry && ! $fotoKaryawanRegistry->isAvailable())
                                <span class="small text-body-secondary" role="status">Foto sedang diproses</span>
                                @endif
                                <div>
                                    <strong>{{ $karyawan->nama_lengkap }}</strong>
                                    <small>{{ $karyawan->nik }} · {{ $karyawan->jabatan }}</small>
                                </div>
                            </div>

                            <nav
                                class="nav nav-pills flex-column employee-detail-nav"
                                id="karyawanShowTab"
                                role="tablist"
                                aria-label="Bagian detail karyawan"
                                aria-orientation="vertical"
                            >
                                <div class="settings-nav__label">Informasi Karyawan</div>

                                <button class="settings-nav__link employee-detail-nav__link active" id="show-tab-identitas-btn" data-bs-toggle="pill" data-bs-target="#show-tab-identitas" type="button" role="tab" aria-controls="show-tab-identitas" aria-selected="true">
                                    <i class="bi bi-person-vcard" aria-hidden="true"></i>
                                    <span>
                                        <strong>Data Identitas</strong>
                                        <small>Pribadi, keluarga, dan alamat</small>
                                    </span>
                                </button>

                                <button class="settings-nav__link employee-detail-nav__link" id="show-tab-kepegawaian-btn" data-bs-toggle="pill" data-bs-target="#show-tab-kepegawaian" type="button" role="tab" aria-controls="show-tab-kepegawaian" aria-selected="false">
                                    <i class="bi bi-briefcase" aria-hidden="true"></i>
                                    <span>
                                        <strong>Data Kepegawaian</strong>
                                        <small>Unit, jabatan, SK, dan gaji</small>
                                    </span>
                                </button>

                                <button class="settings-nav__link employee-detail-nav__link" id="show-tab-dokumen-btn" data-bs-toggle="pill" data-bs-target="#show-tab-dokumen" type="button" role="tab" aria-controls="show-tab-dokumen" aria-selected="false">
                                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                                    <span>
                                        <strong>
                                            Dokumen Karyawan
                                            @if($karyawan->dokumen->isNotEmpty())
                                                <span class="badge bg-secondary rounded-pill">{{ $karyawan->dokumen->count() }}</span>
                                            @endif
                                        </strong>
                                        <small>Dokumen awal karyawan</small>
                                    </span>
                                </button>

                                @can('viewHistory', $karyawan)
                                    <button class="settings-nav__link employee-detail-nav__link" id="show-tab-riwayat-btn" data-bs-toggle="pill" data-bs-target="#show-tab-riwayat" type="button" role="tab" aria-controls="show-tab-riwayat" aria-selected="false">
                                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                                        <span>
                                            <strong>
                                                Histori Perubahan
                                                @if($karyawan->riwayatPerubahan->isNotEmpty())
                                                    <span class="badge bg-secondary rounded-pill">{{ $karyawan->riwayatPerubahan->count() }}</span>
                                                @endif
                                            </strong>
                                            <small>Nilai lama, baru, dan dokumen</small>
                                        </span>
                                    </button>
                                @endcan
                            </nav>
                        </div>
                    </aside>

                    <div class="col-lg-8 col-xl-9">
                        <div class="tab-content employee-detail-content" id="karyawanShowTabContent">
                    <div class="tab-pane fade show active" id="show-tab-identitas" role="tabpanel" aria-labelledby="show-tab-identitas-btn">
                        <header class="employee-detail-content__header">
                            <h2>Data Identitas</h2>
                            <p>Informasi pribadi, keluarga, pendidikan, dan alamat karyawan.</p>
                        </header>
                        <dl class="row mb-0 g-3">
                            <dt class="col-sm-4 text-muted">NIK (Internal)</dt>
                            <dd class="col-sm-8 fw-bold">{{ $karyawan->nik }}</dd>

                            <dt class="col-sm-4 text-muted">Nama Lengkap</dt>
                            <dd class="col-sm-8">{{ $karyawan->nama_lengkap }}</dd>

                            <dt class="col-sm-4 text-muted">Tempat, Tanggal Lahir</dt>
                            <dd class="col-sm-8">{{ $karyawan->tempat_lahir ?? '-' }}, {{ $karyawan->tanggal_lahir->translatedFormat('d F Y') }} ({{ $usia }} tahun)</dd>

                            <dt class="col-sm-4 text-muted">Kategori Usia</dt>
                            <dd class="col-sm-8">{{ $kategoriUsia }}</dd>

                            <dt class="col-sm-4 text-muted">Jenis Kelamin</dt>
                            <dd class="col-sm-8">{{ $karyawan->jenis_kelamin ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Agama</dt>
                            <dd class="col-sm-8">{{ $karyawan->agama ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Status Perkawinan</dt>
                            <dd class="col-sm-8">{{ $karyawan->status_perkawinan ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Nomor KTP/NIK</dt>
                            <dd class="col-sm-8">{{ $karyawan->nomor_ktp ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">NPWP</dt>
                            <dd class="col-sm-8">{{ $karyawan->npwp ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Pendidikan Terakhir</dt>
                            <dd class="col-sm-8">{{ $karyawan->pendidikan_terakhir ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Jurusan</dt>
                            <dd class="col-sm-8">{{ $karyawan->jurusan ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Sekolah/Perguruan Tinggi</dt>
                            <dd class="col-sm-8">{{ $karyawan->nama_sekolah ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Tahun Lulus</dt>
                            <dd class="col-sm-8">{{ $karyawan->tahun_lulus ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Nama Pasangan</dt>
                            <dd class="col-sm-8">{{ $karyawan->nama_pasangan ?: '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Jumlah Anak</dt>
                            <dd class="col-sm-8">{{ $karyawan->jumlah_anak ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Alamat sesuai KTP</dt>
                            <dd class="col-sm-8">{{ $karyawan->alamat_ktp ?: '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Alamat Domisili</dt>
                            <dd class="col-sm-8">{{ $karyawan->alamat_domisili ?: '-' }}</dd>
                        </dl>
                    </div>

                    <div class="tab-pane fade" id="show-tab-kepegawaian" role="tabpanel" aria-labelledby="show-tab-kepegawaian-btn">
                        <header class="employee-detail-content__header">
                            <h2>Data Kepegawaian</h2>
                            <p>Penempatan, hubungan kerja, dasar pengangkatan, dan informasi gaji.</p>
                        </header>
                        <dl class="row mb-0 g-3">
                            <dt class="col-sm-4 text-muted">Unit Kerja/Bagian</dt>
                            <dd class="col-sm-8">{{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}</dd>

                            <dt class="col-sm-4 text-muted">Tanggal Masuk Kerja</dt>
                            <dd class="col-sm-8">{{ $karyawan->tanggal_masuk_kerja?->translatedFormat('d F Y') ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Masa Kerja</dt>
                            <dd class="col-sm-8">{{ $masaKerja }}</dd>

                            <dt class="col-sm-4 text-muted">Jabatan</dt>
                            <dd class="col-sm-8">{{ $karyawan->jabatan }}</dd>

                            <dt class="col-sm-4 text-muted">Nomor SK Pengangkatan</dt>
                            <dd class="col-sm-8">{{ $karyawan->nomor_sk_pengangkatan ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Tanggal SK Pengangkatan</dt>
                            <dd class="col-sm-8">{{ $karyawan->tanggal_sk_pengangkatan?->translatedFormat('d F Y') ?? '-' }}</dd>

                            <dt class="col-sm-4 text-muted">Atasan Langsung</dt>
                            <dd class="col-sm-8">{{ $karyawan->atasanLangsung?->nama_lengkap ?? '-' }}</dd>

                            @can('viewSalary', $karyawan)
                                <dt class="col-sm-4 text-muted">Gaji Pokok</dt>
                                <dd class="col-sm-8 fw-bold">Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</dd>
                            @endcan
                        </dl>
                    </div>

                    <div class="tab-pane fade" id="show-tab-dokumen" role="tabpanel" aria-labelledby="show-tab-dokumen-btn">
                        <header class="employee-detail-content__header">
                            <h2>Dokumen Karyawan</h2>
                            <p>Dokumen awal yang tersimpan saat data karyawan dibuat.</p>
                        </header>
                        <p class="text-body-secondary small">
                            <i class="bi bi-info-circle"></i>
                            Dokumen yang menjadi dasar perubahan baru diunggah melalui
                            <strong>Edit Data Karyawan</strong> dan tersimpan bersama histori perubahannya.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Nama File</th>
                                        <th>Tanggal Unggah</th>
                                        <th class="text-nowrap table-col-width-120">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($karyawan->dokumen as $dokumen)
                                    @php($dokumenRegistry = $dokumen->storedFiles->first())
                                    <tr>
                                        <td>{{ $dokumen->jenis_dokumen }}</td>
                                        <td>{{ $dokumen->nama_asli }}</td>
                                        <td>{{ $dokumen->created_at->translatedFormat('d F Y') }}</td>
                                        <td class="text-nowrap">
                                            <div class="table-actions">
                                                @if(! $dokumenRegistry || $dokumenRegistry->isAvailable())
                                                <a
                                                    class="btn btn-sm btn-action btn-action-neutral"
                                                    href="{{ route('karyawan.dokumen.download', [$karyawan, $dokumen]) }}"
                                                    target="_blank"
                                                    aria-label="Lihat {{ $dokumen->nama_asli }}"
                                                    title="Lihat/Unduh">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @else
                                                <span class="small text-body-secondary" role="status">Sedang diproses</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <x-empty-row :colspan="4">Belum ada dokumen.</x-empty-row>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @can('viewHistory', $karyawan)
                        <div class="tab-pane fade" id="show-tab-riwayat" role="tabpanel" aria-labelledby="show-tab-riwayat-btn">
                            <header class="employee-detail-content__header">
                                <h2>Histori Perubahan</h2>
                                <p>Jejak nilai lama dan baru, tanggal berlaku, pelaku, serta dokumen pendukung.</p>
                            </header>
                            @include('karyawan._riwayat')
                        </div>
                    @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>

</x-app-page>

@if($jenisPerubahanTersedia !== [])
    @include('karyawan._modal-perubahan')
@endif
@endsection
