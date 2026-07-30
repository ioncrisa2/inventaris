@php
    $modalId = 'cetakSlipGajiMassalModal';
    $selectedMode = old('mode', 'periode');
    $paperLayoutDefault = \App\Support\SlipGajiPaperLayout::normalize(
        old('paper_layout', $paperLayoutDefault ?? \App\Support\SlipGajiPaperLayout::DEFAULT)
    );
    $openOnLoad = old('_slip_massal') === '1' && $errors->any();
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
    data-slip-print-modal
    data-slip-print-filter-modal
    data-default-action="{{ route('transaksi-gaji.cetak-massal') }}"
    data-open-on-load="{{ $openOnLoad ? 'true' : 'false' }}"
>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form
                method="GET"
                action="{{ route('transaksi-gaji.cetak-massal') }}"
                data-slip-print-form
            >
                <input type="hidden" name="_slip_massal" value="1">

                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="{{ $modalId }}Label">Cetak Slip Gaji Massal</h2>
                        <p class="mb-0 mt-1 text-body-secondary small">
                            Tentukan cakupan transaksi, susunan kertas, dan penanda tangan.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    @if($openOnLoad)
                        <div class="alert alert-danger" role="alert">
                            <strong>Filter cetak belum dapat diproses.</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <fieldset class="mb-4">
                        <legend class="form-label">Cakupan slip</legend>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input
                                    class="btn-check"
                                    type="radio"
                                    name="mode"
                                    id="{{ $modalId }}ModePeriode"
                                    value="periode"
                                    @checked($selectedMode === 'periode')
                                >
                                <label class="btn slip-print-choice w-100 h-100" for="{{ $modalId }}ModePeriode">
                                    <strong class="slip-print-choice__title">Semua karyawan</strong>
                                    <small class="slip-print-choice__description">Cetak seluruh slip dalam satu bulan.</small>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <input
                                    class="btn-check"
                                    type="radio"
                                    name="mode"
                                    id="{{ $modalId }}ModeKaryawan"
                                    value="karyawan"
                                    @checked($selectedMode === 'karyawan')
                                >
                                <label class="btn slip-print-choice w-100 h-100" for="{{ $modalId }}ModeKaryawan">
                                    <strong class="slip-print-choice__title">Satu karyawan</strong>
                                    <small class="slip-print-choice__description">Cetak beberapa periode milik satu karyawan.</small>
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    <div
                        class="row g-3 mb-4"
                        data-print-filter-panel="periode"
                        @if($selectedMode !== 'periode') hidden @endif
                    >
                        <div class="col-sm-6">
                            <label class="form-label" for="{{ $modalId }}Bulan">Bulan</label>
                            <select
                                class="form-select @error('bulan') is-invalid @enderror"
                                id="{{ $modalId }}Bulan"
                                name="bulan"
                                @if($selectedMode === 'periode') required @else disabled @endif
                            >
                                @for($bulan = 1; $bulan <= 12; $bulan++)
                                    <option value="{{ $bulan }}" @selected((int) old('bulan', now()->month) === $bulan)>
                                        {{ \Illuminate\Support\Carbon::createFromDate(2000, $bulan, 1)->translatedFormat('F') }}
                                    </option>
                                @endfor
                            </select>
                            @error('bulan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="{{ $modalId }}Tahun">Tahun</label>
                            <input
                                class="form-control @error('tahun') is-invalid @enderror"
                                id="{{ $modalId }}Tahun"
                                name="tahun"
                                type="number"
                                min="2000"
                                max="2100"
                                value="{{ old('tahun', now()->year) }}"
                                @if($selectedMode === 'periode') required @else disabled @endif
                            >
                            @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div
                        class="row g-3 mb-4"
                        data-print-filter-panel="karyawan"
                        @if($selectedMode !== 'karyawan') hidden @endif
                    >
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="{{ $modalId }}Karyawan">Karyawan</label>
                            <select
                                class="form-select @error('karyawan_id') is-invalid @enderror"
                                id="{{ $modalId }}Karyawan"
                                name="karyawan_id"
                                @if($selectedMode === 'karyawan') required @else disabled @endif
                            >
                                <option value="">Pilih karyawan yang memiliki transaksi gaji</option>
                                @foreach($karyawanCetak as $karyawan)
                                    <option value="{{ $karyawan->id }}" @selected((int) old('karyawan_id') === $karyawan->id)>
                                        {{ $karyawan->nama_lengkap }}
                                        @if($karyawan->tanggal_mengundurkan_diri) — Nonaktif @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('karyawan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <label class="form-label" for="{{ $modalId }}PeriodeAwal">Periode awal</label>
                            <input
                                class="form-control @error('periode_awal') is-invalid @enderror"
                                id="{{ $modalId }}PeriodeAwal"
                                name="periode_awal"
                                type="month"
                                min="2000-01"
                                max="2100-12"
                                value="{{ old('periode_awal', now()->format('Y-m')) }}"
                                @if($selectedMode === 'karyawan') required @else disabled @endif
                            >
                            @error('periode_awal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label" for="{{ $modalId }}PeriodeAkhir">Periode akhir</label>
                            <input
                                class="form-control @error('periode_akhir') is-invalid @enderror"
                                id="{{ $modalId }}PeriodeAkhir"
                                name="periode_akhir"
                                type="month"
                                min="{{ old('periode_awal', '2000-01') }}"
                                max="2100-12"
                                value="{{ old('periode_akhir', now()->format('Y-m')) }}"
                                @if($selectedMode === 'karyawan') required @else disabled @endif
                            >
                            @error('periode_akhir') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="form-label">Pembagian kertas F4 portrait</legend>
                        <div class="row g-2">
                            @foreach([
                                \App\Support\SlipGajiPaperLayout::LEFT_RIGHT => ['Kiri–kanan', 'Garis pembagi vertikal'],
                                \App\Support\SlipGajiPaperLayout::TOP_BOTTOM => ['Atas–bawah', 'Garis pembagi horizontal'],
                            ] as $layout => [$label, $description])
                                @php($inputId = $modalId.'PaperLayout'.str_replace('_', '', ucfirst($layout)))
                                <div class="col-sm-6">
                                    <input
                                        type="radio"
                                        class="btn-check"
                                        name="paper_layout"
                                        value="{{ $layout }}"
                                        id="{{ $inputId }}"
                                        @checked($paperLayoutDefault === $layout)
                                    >
                                    <label class="btn slip-print-choice w-100 h-100" for="{{ $inputId }}">
                                        <strong class="slip-print-choice__title">{{ $label }}</strong>
                                        <small class="slip-print-choice__description">{{ $description }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}DibuatOleh">Dibuat oleh</label>
                            <select
                                class="form-select @error('dibuat_oleh_id') is-invalid @enderror"
                                id="{{ $modalId }}DibuatOleh"
                                name="dibuat_oleh_id"
                                required
                            >
                                <option value="">Pilih karyawan aktif</option>
                                @foreach($penandaTangan as $karyawan)
                                    <option value="{{ $karyawan->id }}" @selected((int) old('dibuat_oleh_id') === $karyawan->id)>
                                        {{ $karyawan->nama_lengkap }} — {{ $karyawan->jabatan ?: 'Tanpa jabatan' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('dibuat_oleh_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $modalId }}Mengetahui">Mengetahui</label>
                            <select
                                class="form-select @error('mengetahui_id') is-invalid @enderror"
                                id="{{ $modalId }}Mengetahui"
                                name="mengetahui_id"
                                required
                            >
                                <option value="">Pilih karyawan aktif</option>
                                @foreach($penandaTangan as $karyawan)
                                    <option value="{{ $karyawan->id }}" @selected((int) old('mengetahui_id') === $karyawan->id)>
                                        {{ $karyawan->nama_lengkap }} — {{ $karyawan->jabatan ?: 'Tanpa jabatan' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('mengetahui_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Penanda tangan harus berbeda. Maksimal 100 slip dapat dicetak dalam satu preview.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-printer" aria-hidden="true"></i>
                        Buka Preview
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
