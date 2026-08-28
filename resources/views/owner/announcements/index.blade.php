@extends('layouts.app')

@section('title', 'Pengumuman Platform')

@section('content')
<x-app-page>
    <x-page-header title="Pengumuman Platform" subtitle="Kirim pemberitahuan platform kepada seluruh Admin Primer atau satu koperasi tertentu." />
    <x-flash-alert />

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><span>Buat Draf</span></div>
                <form method="POST" action="{{ route('owner.announcements.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3"><x-form.input name="title" label="Judul" required maxlength="200" /></div>
                        <div class="mb-3">
                            <label class="form-label" for="body">Isi pengumuman <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="7" maxlength="10000" required>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-form.select name="priority" label="Prioritas" :options="collect(['info' => 'Informasi', 'warning' => 'Peringatan', 'critical' => 'Kritis'])" value="info" required />
                            </div>
                            <div class="col-md-6">
                                <x-form.select name="target_koperasi_id" label="Target" :options="$koperasis->pluck('nama', 'id')" placeholder="Semua Admin Primer" help="Kosongkan untuk seluruh koperasi." />
                            </div>
                            <div class="col-md-6"><x-form.input name="starts_at" label="Mulai tampil" type="datetime-local" /></div>
                            <div class="col-md-6"><x-form.input name="ends_at" label="Selesai tampil" type="datetime-local" /></div>
                        </div>
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary" type="submit">Simpan Draf</button></div>
                </form>
            </div>
        </div>
        <div class="col-xl-7">
            <x-data-table :paginator="$announcements" title="Riwayat Pengumuman">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Pengumuman</th><th>Target</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse($announcements as $announcement)
                            <tr>
                                <td><strong>{{ $announcement->title }}</strong><div class="small text-body-secondary">{{ $announcement->created_at->translatedFormat('d F Y H:i') }}</div></td>
                                <td>{{ $announcement->target_koperasi_id ? ($koperasis->firstWhere('id', $announcement->target_koperasi_id)?->nama ?? 'Koperasi tidak tersedia') : 'Semua Admin Primer' }}</td>
                                <td><span class="badge {{ $announcement->published_at ? 'bg-success' : 'bg-secondary' }}">{{ $announcement->published_at ? 'Terbit' : 'Draf' }}</span></td>
                                <td class="text-end">
                                    @if(! $announcement->published_at)
                                        <form method="POST" action="{{ route('owner.announcements.publish', $announcement) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-warning" type="submit">Terbitkan</button>
                                        </form>
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="4">Belum ada pengumuman.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </x-data-table>
        </div>
    </div>
</x-app-page>
@endsection
