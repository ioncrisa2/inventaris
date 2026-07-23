@extends('layouts.app')

@section('title', 'Detail Karyawan - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header
            title="{{ $karyawan->nama_lengkap }}"
            subtitle="{{ $karyawan->nik }} — {{ $karyawan->jabatan }} — {{ $karyawan->unitKerja?->nama_unit ?? 'Belum ditentukan' }}"
        >
            <x-slot:actions>
                <div class="d-flex gap-2">
                    <a class="btn btn-success" href="{{ route('absensi.show', $karyawan) }}">
                        <i class="bi bi-calendar-check"></i>
                        Lihat Absensi
                    </a>
                    <a class="btn btn-primary" href="{{ route('karyawan.edit', $karyawan) }}">
                        <i class="bi bi-pencil"></i>
                        Edit
                    </a>
                    <a class="btn btn-light" href="{{ route('karyawan.index') }}">Kembali</a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="card content-narrow">
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
                <x-image-preview
                    :src="$karyawan->foto_karyawan ? \Illuminate\Support\Facades\Storage::url($karyawan->foto_karyawan) : null"
                    alt="Foto {{ $karyawan->nama_lengkap }}"
                    icon="bi-person"
                    size="avatar"
                    class="mb-3"
                />

                <ul class="nav nav-tabs mb-3" id="karyawanShowTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="show-tab-identitas-btn" data-bs-toggle="tab" data-bs-target="#show-tab-identitas" type="button" role="tab" aria-controls="show-tab-identitas" aria-selected="true">
                            Data Identitas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="show-tab-kepegawaian-btn" data-bs-toggle="tab" data-bs-target="#show-tab-kepegawaian" type="button" role="tab" aria-controls="show-tab-kepegawaian" aria-selected="false">
                            Data Kepegawaian
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="show-tab-dokumen-btn" data-bs-toggle="tab" data-bs-target="#show-tab-dokumen" type="button" role="tab" aria-controls="show-tab-dokumen" aria-selected="false">
                            Dokumen Karyawan
                            @if($karyawan->dokumen->isNotEmpty())
                            <span class="badge bg-secondary rounded-pill">{{ $karyawan->dokumen->count() }}</span>
                            @endif
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="karyawanShowTabContent">
                    <div class="tab-pane fade show active" id="show-tab-identitas" role="tabpanel" aria-labelledby="show-tab-identitas-btn">
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
                        </dl>
                    </div>

                    <div class="tab-pane fade" id="show-tab-kepegawaian" role="tabpanel" aria-labelledby="show-tab-kepegawaian-btn">
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

                            <dt class="col-sm-4 text-muted">Gaji Pokok</dt>
                            <dd class="col-sm-8 fw-bold">Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</dd>
                        </dl>
                    </div>

                    <div class="tab-pane fade" id="show-tab-dokumen" role="tabpanel" aria-labelledby="show-tab-dokumen-btn">
                        <p class="text-body-secondary small">
                            <i class="bi bi-info-circle"></i>
                            Untuk menambah dokumen baru, gunakan tab Dokumen Pendukung di <a href="{{ route('karyawan.edit', $karyawan) }}">halaman edit</a>.
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
                                    <tr>
                                        <td>{{ $dokumen->jenis_dokumen }}</td>
                                        <td>{{ $dokumen->nama_asli }}</td>
                                        <td>{{ $dokumen->created_at->translatedFormat('d F Y') }}</td>
                                        <td class="text-nowrap">
                                            <div class="table-actions">
                                                <a
                                                    class="btn btn-sm btn-action btn-action-neutral"
                                                    href="{{ route('karyawan.dokumen.download', [$karyawan, $dokumen]) }}"
                                                    target="_blank"
                                                    aria-label="Lihat {{ $dokumen->nama_asli }}"
                                                    title="Lihat/Unduh">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <x-delete-button
                                                    :url="route('karyawan.dokumen.destroy', [$karyawan, $dokumen])"
                                                    :message="'Yakin ingin menghapus dokumen &quot;'.$dokumen->nama_asli.'&quot;?'"
                                                    :label="'Hapus '.$dokumen->nama_asli"
                                                />
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
                </div>
            </div>
        </div>

</x-app-page>
@endsection
