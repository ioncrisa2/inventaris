@extends('layouts.print')

@section('title', 'QR Code - '.$barang->kode_barang)
@section('back_url', route('barang.show', $barang))
@section('print_layout', 'qr-label')
@section('content')
<article class="qr-label">
    <h1 class="qr-label__name">{{ $barang->nama_barang }}</h1>
    <p class="qr-label__code">{{ $barang->kode_barang }}</p>

    <div class="qr-label__image" aria-label="QR Code menuju detail {{ $barang->nama_barang }}">
        {!! $qrSvg !!}
    </div>
    <p class="qr-label__hint">Pindai untuk membuka detail barang.</p>
</article>
@endsection
