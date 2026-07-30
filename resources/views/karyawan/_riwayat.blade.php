@php($labelJenis = collect(\App\Support\KaryawanPerubahanSchema::types())->pluck('label'))

<div class="employee-history">
    @forelse($karyawan->riwayatPerubahan as $riwayat)
        <article class="employee-history__item">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <h3 class="h6 mb-1">{{ $labelJenis[$riwayat->jenis_perubahan] ?? 'Perubahan Karyawan' }}</h3>
                    <div class="small text-body-secondary">
                        Berlaku {{ $riwayat->tanggal_berlaku->translatedFormat('d F Y') }}
                        · Dicatat {{ $riwayat->created_at->translatedFormat('d F Y H:i') }}
                    </div>
                </div>
                <span class="badge text-bg-light align-self-start">
                    {{ $riwayat->pelaku?->name ?? $riwayat->nama_pelaku_snapshot }}
                </span>
            </div>

            <p class="mb-3">{{ $riwayat->alasan }}</p>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Nilai Lama</th>
                            <th>Nilai Baru</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($riwayat->perubahan as $perubahan)
                            <tr>
                                <th class="fw-medium">{{ $perubahan->label }}</th>
                                @if($perubahan->field === 'gaji_pokok' && ! auth()->user()->can('viewSalary', $karyawan))
                                    <td class="text-body-secondary">Dibatasi</td>
                                    <td class="text-body-secondary">Dibatasi</td>
                                @else
                                    <td>{{ $perubahan->tampilan_lama ?: '—' }}</td>
                                    <td class="fw-semibold">{{ $perubahan->tampilan_baru ?: '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($riwayat->dokumen->isNotEmpty())
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @foreach($riwayat->dokumen as $dokumen)
                        <a
                            class="btn btn-sm btn-light"
                            href="{{ route('karyawan.riwayat.dokumen.download', [$karyawan, $riwayat, $dokumen]) }}"
                            target="_blank"
                        >
                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                            {{ $dokumen->nama_asli }}
                        </a>
                    @endforeach
                </div>
            @endif
        </article>
    @empty
        <x-empty-state icon="bi-clock-history" title="Belum ada histori perubahan">
            Perubahan terstruktur yang dilakukan setelah fitur ini aktif akan muncul di sini.
        </x-empty-state>
    @endforelse
</div>
