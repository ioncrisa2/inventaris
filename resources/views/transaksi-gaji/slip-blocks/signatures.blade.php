<div class="salary-slip__signatures salary-slip__signatures--{{ $block['variant'] }}">
    <div class="salary-slip__signature">
        <span>Dibuat oleh,</span>
        <div class="salary-slip__signature-space"></div>
        <strong>{{ $dibuatOleh->nama_lengkap }}</strong>
        <small>{{ $dibuatOleh->jabatan ?: 'Karyawan' }}</small>
    </div>
    <div class="salary-slip__signature">
        <span>Diterima oleh,</span>
        <div class="salary-slip__signature-space"></div>
        <strong>{{ $transaksi->karyawan->nama_lengkap }}</strong>
        <small>{{ $transaksi->karyawan->jabatan ?: 'Karyawan' }}</small>
    </div>
    <div class="salary-slip__signature">
        <span>Mengetahui,</span>
        <div class="salary-slip__signature-space"></div>
        <strong>{{ $mengetahui->nama_lengkap }}</strong>
        <small>{{ $mengetahui->jabatan ?: 'Karyawan' }}</small>
    </div>
</div>
