@extends('layouts.print')

@section('title', 'Barcode - '.$barang->kode_barang)
@section('back_url', route('barang.show', $barang))
@section('print_layout', 'barcode-label')
@section('content')
<article class="asset-label">
    <img
        class="asset-label__logo"
        src="{{ asset('assets/img/logo-koperasi.png') }}"
        alt=""
        aria-hidden="true">
    <h1 class="asset-label__name">{{ $barang->nama_barang }}</h1>

    <div class="asset-label__barcode" aria-label="Barcode {{ $barang->kode_barang }}">
        {!! $barcodeSvg !!}
    </div>
    <p class="asset-label__code">{{ $barang->kode_barang }}</p>
</article>
@endsection
