@extends('layouts.print')

@section('title', 'Barcode Inventaris Terpilih')
@section('back_url', route('barang.index'))
@section('print_layout', 'a4-portrait')
@section('screen_actions')
<span class="screen-actions__summary" id="printSettingsSummary" aria-live="polite">A4 &middot; Potret &middot; Jarak 7 mm</span>
<button
    class="screen-actions__settings"
    id="togglePrintSettings"
    type="button"
    aria-expanded="false"
    aria-controls="barcodePrintSettings">
    Pengaturan Cetak
</button>
@endsection

@section('screen_settings')
<section class="screen-settings" id="barcodePrintSettings" aria-labelledby="barcodePrintSettingsTitle" hidden>
    <div class="screen-settings__header">
        <div>
            <h2 id="barcodePrintSettingsTitle">Pengaturan cetak barcode</h2>
            <p>Perubahan langsung diterapkan pada preview dan hasil cetak.</p>
        </div>
        <button class="screen-settings__reset" id="resetPrintSettings" type="button">Reset default</button>
    </div>

    <div class="screen-settings__grid">
        <div class="print-setting-field">
            <label for="barcodePaperSize">Ukuran kertas</label>
            <select id="barcodePaperSize">
                <option value="A4">A4 (210 &times; 297 mm)</option>
                <option value="A5">A5 (148 &times; 210 mm)</option>
                <option value="Letter">Letter (216 &times; 279 mm)</option>
                <option value="Legal">Legal (216 &times; 356 mm)</option>
            </select>
        </div>

        <div class="print-setting-field">
            <fieldset>
                <legend>Orientasi kertas</legend>
                <div class="orientation-options">
                    <label class="orientation-option">
                        <input type="radio" name="barcode_orientation" value="portrait" checked>
                        <span>Potret</span>
                    </label>
                    <label class="orientation-option">
                        <input type="radio" name="barcode_orientation" value="landscape">
                        <span>Lanskap</span>
                    </label>
                </div>
            </fieldset>
        </div>

        <div class="print-setting-field">
            <label for="barcodeLabelSpacing">Jarak antar barcode</label>
            <div class="spacing-control">
                <input id="barcodeLabelSpacing" type="range" min="0" max="20" step="1" value="7">
                <output id="barcodeLabelSpacingValue" for="barcodeLabelSpacing">7 mm</output>
            </div>
        </div>
    </div>
</section>
@endsection

@section('content')
<header class="bulk-barcode-header">
    <div>
        <h1>Barcode Inventaris</h1>
        <p>{{ $barangs->count() }} barang dipilih untuk dicetak.</p>
    </div>
    <span>{{ now()->translatedFormat('d F Y') }}</span>
</header>

<section class="barcode-label-grid" aria-label="Daftar barcode inventaris">
    @foreach($barangs as $barang)
    <article class="barcode-label">
        <img
            class="barcode-label__logo"
            src="{{ asset('assets/img/logo-koperasi.png') }}"
            alt=""
            aria-hidden="true">
        <h2 class="barcode-label__name">{{ $barang->nama_barang }}</h2>
        <p class="barcode-label__unit">{{ $barang->unitKerja?->nama_unit ?? 'Unit belum ditentukan' }}</p>
        <div class="barcode-label__image" aria-label="Barcode {{ $barang->kode_barang }}">
            {!! $barcodeSvgs[$barang->id] !!}
        </div>
        <p class="barcode-label__code">{{ $barang->kode_barang }}</p>
    </article>
    @endforeach
</section>
@endsection
