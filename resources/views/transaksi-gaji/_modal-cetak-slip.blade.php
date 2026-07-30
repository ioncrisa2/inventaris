@php
    $modalId = $modalId ?? 'cetakSlipGajiModal';
    $action = $action ?? '#';
    $bulkGroup = $bulkGroup ?? null;
    $paperLayoutDefault = \App\Support\SlipGajiPaperLayout::normalize(
        $paperLayoutDefault ?? \App\Support\SlipGajiPaperLayout::DEFAULT
    );
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
    data-slip-print-modal
    data-default-action="{{ $action }}"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form
                method="GET"
                action="{{ $action }}"
                target="_blank"
                data-slip-print-form
                @if($bulkGroup)
                    data-bulk-form="{{ $bulkGroup }}"
                    data-bulk-input-name="transaksi_gaji_ids[]"
                @endif
            >
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="{{ $modalId }}Label">Cetak Slip Gaji</h2>
                        <p class="mb-0 mt-1 text-body-secondary small">
                            Atur susunan kertas dan penanda tangan untuk <span data-slip-print-context>{{ $bulkGroup ? 'slip terpilih' : 'slip ini' }}</span>.
                        </p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <fieldset class="mb-3">
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

                    <div class="mb-3">
                        <label class="form-label" for="{{ $modalId }}DibuatOleh">Dibuat oleh</label>
                        <select class="form-select" id="{{ $modalId }}DibuatOleh" name="dibuat_oleh_id" required>
                            <option value="">Pilih karyawan aktif</option>
                            @foreach($penandaTangan as $karyawanPenandaTangan)
                                <option value="{{ $karyawanPenandaTangan->id }}">
                                    {{ $karyawanPenandaTangan->nama_lengkap }} — {{ $karyawanPenandaTangan->jabatan ?: 'Tanpa jabatan' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="{{ $modalId }}Mengetahui">Mengetahui</label>
                        <select class="form-select" id="{{ $modalId }}Mengetahui" name="mengetahui_id" required>
                            <option value="">Pilih karyawan aktif</option>
                            @foreach($penandaTangan as $karyawanPenandaTangan)
                                <option value="{{ $karyawanPenandaTangan->id }}">
                                    {{ $karyawanPenandaTangan->nama_lengkap }} — {{ $karyawanPenandaTangan->jabatan ?: 'Tanpa jabatan' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Penanda tangan harus berbeda. Preview dibuka di tab baru tanpa mengubah format aktif.</div>
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
