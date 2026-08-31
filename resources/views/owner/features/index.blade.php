@extends('layouts.app')

@section('title', 'Akses Fitur Platform')

@section('content')
<x-app-page>
    <x-page-header
        title="Akses Fitur"
        subtitle="Matikan akses menu operasional secara global tanpa menghapus program atau data yang sudah tersimpan."
    />
    <x-flash-alert />

    <section class="platform-feature-summary mb-4" aria-label="Ringkasan akses fitur">
        <div class="platform-feature-summary__status">
            <span class="platform-feature-summary__icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
            <div>
                <strong>{{ $disabledCount === 0 ? 'Semua fitur tersedia' : $disabledCount.' fitur sedang ditutup' }}</strong>
                <p>Perubahan berlaku untuk Admin Primer, Super Admin, dan seluruh pengguna tenant. System Owner tetap memiliki akses pemulihan.</p>
            </div>
        </div>
        <dl>
            <div><dt>Aktif</dt><dd>{{ $enabledCount }}</dd></div>
            <div><dt>Nonaktif</dt><dd>{{ $disabledCount }}</dd></div>
        </dl>
    </section>

    <div class="platform-feature-stack">
        @foreach($featureGroups as $category => $features)
            <section class="platform-feature-section" aria-labelledby="feature-{{ Str::slug($category) }}">
                <header>
                    <h2 id="feature-{{ Str::slug($category) }}">{{ $category }}</h2>
                    <span>{{ $features->where('enabled', true)->count() }} dari {{ $features->count() }} aktif</span>
                </header>

                <div class="platform-feature-list">
                    @foreach($features as $feature)
                        <article class="platform-feature-row {{ $feature['enabled'] ? '' : 'is-disabled' }}">
                            <span class="platform-feature-row__icon" aria-hidden="true">
                                <i class="bi {{ $feature['icon'] }}"></i>
                            </span>
                            <div class="platform-feature-row__content">
                                <div class="platform-feature-row__title">
                                    <h3>{{ $feature['label'] }}</h3>
                                    <span class="badge {{ $feature['enabled'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $feature['enabled'] ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <p>{{ $feature['description'] }}</p>
                                @if($feature['updated_at'])
                                    <small>
                                        Terakhir diubah {{ $feature['updated_at']->locale('id')->diffForHumans() }}
                                        @if($feature['updated_by']) oleh {{ $feature['updated_by'] }} @endif
                                    </small>
                                @else
                                    <small>Belum pernah diubah · mengikuti status bawaan aktif</small>
                                @endif
                            </div>
                            <form
                                method="POST"
                                action="{{ route('owner.features.update', $feature['key']) }}"
                                data-feature-toggle-form
                                data-confirm-message="{{ $feature['enabled'] ? 'Nonaktifkan '.$feature['label'].' untuk seluruh pengguna termasuk Super Admin?' : 'Aktifkan kembali '.$feature['label'].' untuk seluruh pengguna?' }}"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="enabled" value="{{ $feature['enabled'] ? '0' : '1' }}">
                                <button class="btn btn-sm {{ $feature['enabled'] ? 'btn-outline-danger' : 'btn-primary' }}" type="submit">
                                    {{ $feature['enabled'] ? 'Nonaktifkan' : 'Aktifkan kembali' }}
                                </button>
                            </form>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <p class="owner-observability-footnote">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        Menonaktifkan fitur tidak menghapus tabel, file lampiran, atau riwayat aktivitas. Dashboard, autentikasi, notifikasi, dan panel System Owner sengaja tidak dapat dimatikan dari halaman ini.
    </p>
</x-app-page>
@endsection
