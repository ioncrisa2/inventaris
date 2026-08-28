@extends('layouts.app')

@section('title', 'Maintenance Platform')

@section('content')
<x-app-page>
    <x-page-header title="Maintenance Platform" subtitle="Batasi akses pengguna saat pembaruan aplikasi berlangsung. System Owner tetap dapat mengakses area owner." />
    <x-flash-alert />

    <div class="card content-narrow">
        <div class="card-header d-flex justify-content-between align-items-center gap-3">
            <span>Soft Maintenance</span>
            <span class="badge {{ $setting?->enabled ? 'bg-warning text-dark' : 'bg-success' }}">
                {{ $setting?->enabled ? 'Aktif / Terjadwal' : 'Nonaktif' }}
            </span>
        </div>
        <form method="POST" action="{{ route('owner.maintenance.update') }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="message">Pesan kepada pengguna</label>
                    <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="4" maxlength="1000">{{ old('message', $setting?->message) }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="starts_at">Mulai</label>
                        <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $setting?->starts_at?->format('Y-m-d\TH:i')) }}">
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ends_at">Perkiraan selesai</label>
                        <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $setting?->ends_at?->format('Y-m-d\TH:i')) }}">
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <p class="form-text mt-3 mb-0">Gunakan hard maintenance dari deployment/server untuk migrasi yang membuat aplikasi benar-benar tidak aman menerima request.</p>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                @if($setting?->enabled)
                    <button class="btn btn-outline-success" type="submit" form="disable-maintenance-form">Nonaktifkan</button>
                @endif
                <button class="btn btn-warning" type="submit">Aktifkan / Jadwalkan</button>
            </div>
        </form>
        @if($setting?->enabled)
            <form id="disable-maintenance-form" method="POST" action="{{ route('owner.maintenance.destroy') }}">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
</x-app-page>
@endsection
