@extends('layouts.print')

@php
    $slipCount = $slipPages->sum(fn ($page) => $page->count());
    $firstSlip = $slipPages->first()?->first();
    $identitas = app(\App\Services\IdentitasAplikasiService::class);
    $paperLayoutClass = str_replace('_', '-', $paperLayout);
@endphp

@section('title', $isBulk
    ? 'Slip Gaji Massal'
    : 'Slip Gaji - '.$firstSlip['transaksi']->karyawan->nama_lengkap)
@section('back_url', $backUrl)
@section('print_layout', 'f4-portrait')

@section('screen_actions')
    <span class="screen-actions__summary">
        {{ $slipCount }} slip · {{ $slipPages->count() }} lembar F4 ·
        {{ \App\Support\SlipGajiPaperLayout::label($paperLayout) }}
    </span>
@endsection

@section('content')
    <div class="salary-slip-document">
        @foreach($slipPages as $page)
            <section
                class="salary-slip-sheet salary-slip-sheet--{{ $paperLayoutClass }} {{ $page->count() === 1 && $page->first()['is_full_page'] ? 'salary-slip-sheet--single' : '' }}"
                aria-label="Lembar slip gaji {{ $loop->iteration }}"
            >
                @foreach($page as $slip)
                    @include('transaksi-gaji._slip', [
                        'slip' => $slip,
                        'dibuatOleh' => $dibuatOleh,
                        'mengetahui' => $mengetahui,
                        'identitas' => $identitas,
                        'printedAt' => $printedAt,
                        'templateConfiguration' => $templateConfiguration,
                    ])
                @endforeach
            </section>
        @endforeach
    </div>
@endsection
