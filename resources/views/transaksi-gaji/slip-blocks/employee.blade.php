<dl class="salary-slip__identity">
    <div class="salary-slip__identity-primary">
        <dt>Nama</dt>
        <dd>{{ $transaksi->karyawan->nama_lengkap }}</dd>
    </div>
    <div>
        <dt>NIK</dt>
        <dd>{{ $transaksi->karyawan->nik }}</dd>
    </div>
    <div>
        <dt>Unit / Jabatan</dt>
        <dd>{{ $transaksi->karyawan->unitKerja?->nama_unit ?? '—' }} · {{ $transaksi->karyawan->jabatan ?: '—' }}</dd>
    </div>
</dl>
