@extends('layouts.app')

@section('title', 'Editor Slip Gaji - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
    <x-page-header
        title="Editor Slip Gaji"
        subtitle="Susun blok untuk satu slot slip. Satu lembar F4 portrait memuat maksimal dua slip."
    >
        <x-slot:actions>
            <a href="{{ route('pengaturan.edit') }}#format-slip-gaji" class="btn btn-light border">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-flash-alert />

    @error('configuration')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div
        class="slip-template-editor"
        data-slip-template-editor
        data-readonly="{{ $canEdit ? 'false' : 'true' }}"
    >
        <form method="POST" action="{{ route('pengaturan.slip-gaji.draft') }}" data-slip-template-form>
            @csrf
            <input
                type="hidden"
                name="configuration"
                value="{{ json_encode($templateState['configuration']) }}"
                data-slip-template-configuration
            >
            <input type="hidden" name="expected_revision" value="{{ $templateState['draft_revision'] }}">

            <div class="slip-editor-toolbar">
                <div class="slip-editor-toolbar__status">
                    <strong>Draf revisi {{ $templateState['draft_revision'] }}</strong>
                    <small>
                        @if(! $templateState['published_revision'])
                            Belum diterbitkan; perubahan draf belum dipakai saat mencetak dan cetak masih memakai format bawaan.
                        @elseif($templateState['draft_revision'] !== $templateState['published_revision'])
                            Aktif revisi {{ $templateState['published_revision'] }}; perubahan draf belum dipakai saat mencetak.
                        @else
                            Revisi ini aktif dan dipakai saat mencetak.
                        @endif
                    </small>
                </div>
                <div class="slip-editor-toolbar__layout">
                    <label for="slipPaperLayoutDefault">Pembagian default</label>
                    <select
                        class="form-select form-select-sm"
                        id="slipPaperLayoutDefault"
                        data-paper-layout-default
                        @disabled(! $canEdit)
                    >
                        <option value="left_right">Kiri–kanan</option>
                        <option value="top_bottom">Atas–bawah</option>
                    </select>
                </div>
                <div class="slip-editor-toolbar__actions">
                    <button type="button" class="btn btn-sm btn-light border" data-editor-undo disabled>
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        Undo
                    </button>
                    <button type="button" class="btn btn-sm btn-light border" data-editor-redo disabled>
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        Redo
                    </button>
                    <button type="button" class="btn btn-sm btn-light border" data-editor-reset @disabled(! $canEdit)>
                        Reset pola
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        data-editor-preview
                        data-bs-toggle="modal"
                        data-bs-target="#slipTemplatePreviewModal"
                    >
                        <i class="bi bi-eye" aria-hidden="true"></i>
                        Preview F4
                    </button>
                    @if($canEdit)
                        <button type="submit" class="btn btn-sm btn-light border">
                            <i class="bi bi-save" aria-hidden="true"></i>
                            Simpan Draf
                        </button>
                    @endif
                    @if($canPublish)
                        <button
                            type="submit"
                            class="btn btn-sm btn-primary"
                            formaction="{{ route('pengaturan.slip-gaji.publish') }}"
                        >
                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                            Terbitkan untuk Cetak
                        </button>
                    @endif
                </div>
            </div>

            @unless($canEdit)
                <div class="settings-callout mb-3">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Mode hanya-baca</strong>
                        <p>Anda dapat melihat format aktif, tetapi tidak memiliki izin untuk mengubahnya.</p>
                    </div>
                </div>
            @endunless

            <div class="slip-editor-workspace">
                <aside class="slip-editor-panel slip-editor-palette" aria-label="Komponen slip gaji">
                    <div class="slip-editor-panel__header">
                        <h2>Komponen</h2>
                        <p>Tambahkan blok, lalu tarik untuk mengubah urutannya.</p>
                    </div>

                    <div class="slip-editor-palette__group">
                        <h3>Data sistem</h3>
                        @foreach([
                            'logo' => ['bi-image', 'Logo'],
                            'organization' => ['bi-building', 'Identitas koperasi'],
                            'title' => ['bi-type-h1', 'Judul & periode'],
                            'footer' => ['bi-calendar3', 'Tanggal cetak'],
                        ] as $type => [$icon, $label])
                            <button type="button" class="slip-editor-add" data-add-slip-block="{{ $type }}" @disabled(! $canEdit)>
                                <i class="bi {{ $icon }}" aria-hidden="true"></i>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="slip-editor-palette__group">
                        <h3>Dekorasi</h3>
                        @foreach([
                            'text' => ['bi-fonts', 'Teks statis'],
                            'line' => ['bi-dash-lg', 'Garis'],
                            'box' => ['bi-square', 'Kotak'],
                        ] as $type => [$icon, $label])
                            <button type="button" class="slip-editor-add" data-add-slip-block="{{ $type }}" @disabled(! $canEdit)>
                                <i class="bi {{ $icon }}" aria-hidden="true"></i>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="slip-editor-locked-note">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <p><strong>Blok terkunci:</strong> identitas karyawan, data gaji, dan tiga penanda tangan tidak bisa dihapus atau diubah isinya.</p>
                    </div>
                </aside>

                <main class="slip-editor-stage" aria-label="Kanvas slip gaji">
                    <div class="slip-editor-stage__label">
                        <span data-slip-slot-dimensions>Slot slip 100 × 320 mm</span>
                        <small>Tarik blok atau gunakan Alt + panah atas/bawah.</small>
                    </div>
                    <div class="slip-editor-canvas" data-slip-editor-canvas></div>
                </main>

                <aside class="slip-editor-panel slip-editor-properties" aria-label="Properti blok">
                    <div class="slip-editor-panel__header">
                        <h2>Properti</h2>
                        <p data-property-help>Pilih satu blok pada kanvas.</p>
                    </div>

                    <div data-property-empty class="slip-editor-properties__empty">
                        <i class="bi bi-cursor" aria-hidden="true"></i>
                        <span>Belum ada blok dipilih.</span>
                    </div>

                    <fieldset data-property-fields class="slip-editor-properties__fields" disabled hidden>
                        <legend class="visually-hidden">Properti blok terpilih</legend>

                        <div class="mb-3">
                            <label class="form-label" for="slipBlockWidth">Lebar blok</label>
                            <select class="form-select form-select-sm" id="slipBlockWidth" data-block-property="width">
                                <option value="25">25%</option>
                                <option value="50">50%</option>
                                <option value="75">75%</option>
                                <option value="100">100%</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <span class="form-label d-block">Posisi blok</span>
                            <div class="btn-group w-100" role="group" aria-label="Posisi blok">
                                @foreach(['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'] as $value => $label)
                                    <input type="radio" class="btn-check" name="block_align" value="{{ $value }}" id="blockAlign{{ ucfirst($value) }}" data-block-property="align">
                                    <label class="btn btn-sm btn-outline-secondary" for="blockAlign{{ ucfirst($value) }}">{{ $label }}</label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="slipBlockFont">Font</label>
                            <select class="form-select form-select-sm" id="slipBlockFont" data-block-property="font_family">
                                @foreach($fontFamilies as $font)
                                    <option value="{{ $font }}">{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="slipBlockFontSize">Ukuran</label>
                                <input type="number" class="form-control form-control-sm" id="slipBlockFontSize" min="6" max="16" step=".5" data-block-property="font_size">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="slipBlockWeight">Tebal</label>
                                <select class="form-select form-select-sm" id="slipBlockWeight" data-block-property="font_weight">
                                    <option value="400">Normal</option>
                                    <option value="700">Tebal</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="form-label d-block">Rata teks</span>
                            <div class="btn-group w-100" role="group" aria-label="Perataan teks">
                                @foreach(['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'] as $value => $label)
                                    <input type="radio" class="btn-check" name="text_align" value="{{ $value }}" id="textAlign{{ ucfirst($value) }}" data-block-property="text_align">
                                    <label class="btn btn-sm btn-outline-secondary" for="textAlign{{ ucfirst($value) }}">{{ $label }}</label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="slipBlockColor">Warna grayscale</label>
                            <select class="form-select form-select-sm" id="slipBlockColor" data-block-property="color">
                                <option value="black">Hitam</option>
                                <option value="dark">Abu gelap</option>
                                <option value="medium">Abu sedang</option>
                                <option value="light">Abu terang</option>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="slipBlockBefore">Jarak atas</label>
                                <input type="number" class="form-control form-control-sm" id="slipBlockBefore" min="0" max="8" step=".5" data-block-property="spacing_before">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="slipBlockAfter">Jarak bawah</label>
                                <input type="number" class="form-control form-control-sm" id="slipBlockAfter" min="0" max="8" step=".5" data-block-property="spacing_after">
                            </div>
                        </div>

                        <div class="mb-3" data-content-property hidden>
                            <label class="form-label" for="slipBlockContent">Teks</label>
                            <textarea class="form-control form-control-sm" id="slipBlockContent" rows="3" maxlength="300" data-block-property="content"></textarea>
                        </div>

                        <div class="mb-3" data-variant-property hidden>
                            <label class="form-label" for="slipBlockVariant">Pola</label>
                            <select class="form-select form-select-sm" id="slipBlockVariant" data-block-property="variant"></select>
                        </div>

                        <div class="mb-3" data-height-property hidden>
                            <label class="form-label" for="slipBlockHeight">Tinggi kotak (mm)</label>
                            <input type="number" class="form-control form-control-sm" id="slipBlockHeight" min="2" max="30" step="1" data-block-property="height">
                        </div>

                        <div class="d-grid gap-2">
                            <div class="btn-group" role="group" aria-label="Ubah urutan blok">
                                <button type="button" class="btn btn-sm btn-light border" data-move-block="-1">
                                    <i class="bi bi-arrow-up" aria-hidden="true"></i>
                                    Naik
                                </button>
                                <button type="button" class="btn btn-sm btn-light border" data-move-block="1">
                                    <i class="bi bi-arrow-down" aria-hidden="true"></i>
                                    Turun
                                </button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-block>
                                <i class="bi bi-trash" aria-hidden="true"></i>
                                Hapus blok
                            </button>
                        </div>
                    </fieldset>
                </aside>
            </div>
        </form>

        <p class="visually-hidden" aria-live="polite" data-editor-announcement></p>
        <input
            type="hidden"
            value="{{ json_encode($defaultConfiguration) }}"
            data-default-slip-configuration
        >
    </div>

    <div class="modal fade" id="slipTemplatePreviewModal" tabindex="-1" aria-labelledby="slipTemplatePreviewTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="slipTemplatePreviewTitle">Preview F4 portrait</h2>
                        <p class="mb-0 small text-body-secondary">Simulasi satu lembar berisi dua slip. Preview ini memakai data contoh.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body bg-body-tertiary">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <strong class="small">Simulasi pembagian</strong>
                            <div class="small text-body-secondary">Ubah preview tanpa mengubah draf.</div>
                        </div>
                        <select
                            class="form-select form-select-sm w-auto"
                            data-paper-layout-preview
                            aria-label="Pembagian kertas pada preview"
                        >
                            <option value="left_right">Kiri–kanan — garis vertikal</option>
                            <option value="top_bottom">Atas–bawah — garis horizontal</option>
                        </select>
                    </div>
                    <div class="slip-editor-page-preview" data-slip-page-preview>
                        <div class="slip-editor-page-preview__slot" data-slip-preview-slot></div>
                        <div class="slip-editor-page-preview__slot" data-slip-preview-slot></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto small text-body-secondary">F4 210 × 330 mm · margin 5 mm · skala cetak 100%</span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</x-app-page>
@endsection
