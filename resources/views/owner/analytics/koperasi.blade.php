@extends('layouts.app')

@section('title', 'Ringkasan ' . $koperasi['nama'] . ' - System Owner')

@section('content')
    <x-app-page long-footer>
        <x-page-header :title="$koperasi['nama']" subtitle="Ringkasan operasional koperasi dalam bentuk agregat.">
            <x-slot:actions>
                <a class="btn btn-outline-secondary"
                    href="{{ route('owner.analytics', [
                        'tanggal_awal' => $filters['tanggal_awal'],
                        'tanggal_akhir' => $filters['tanggal_akhir'],
                        'modul' => $filters['modul'],
                    ]) }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Semua koperasi
                </a>
            </x-slot:actions>
        </x-page-header>

        <section class="owner-command-bar mb-4">
            @include('owner.partials.analytics-filter', [
                'action' => route('owner.analytics'),
                'filters' => $filters,
                'koperasis' => $analytics['pilihanKoperasi'],
            ])
        </section>

        @include('owner.partials.metric-cards', ['cards' => $analytics['kartu']])

        <div class="owner-analytics-stack mt-4">
            @include('owner.partials.analytics-sections')
        </div>

        <div class="mt-4">@include('owner.partials.privacy-note')</div>
    </x-app-page>
@endsection
