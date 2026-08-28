@php
    $inventaris = $analytics['inventaris'] ?? null;
    $kepegawaian = $analytics['kepegawaian'] ?? null;
    $absensi = $analytics['absensi'] ?? null;
    $penggajian = $analytics['penggajian'] ?? null;
@endphp

@if ($inventaris)
    <section class="owner-module-section" aria-labelledby="ownerInventarisTitle">
        <header class="owner-module-section__header">
            <div><span class="owner-module-section__icon"><i class="bi bi-box-seam"></i></span></div>
            <div>
                <h2 id="ownerInventarisTitle">Inventaris</h2>
                <p>Nilai aset, kondisi terakhir, dan kualitas pencatatan inventaris.</p>
            </div>
        </header>
        <div class="owner-fact-row">
            <div><span>Total
                    barang</span><strong>{{ number_format($inventaris['ringkasan']['total_barang'], 0, ',', '.') }}</strong>
            </div>
            <div><span>Harga perolehan</span><strong>{{ \App\Support\OwnerMetricFormatter::rupiah($inventaris['ringkasan']['total_harga_perolehan']) }}</strong>
            </div>
            <div><span>Nilai buku {{ $inventaris['ringkasan']['tahun_penilaian'] }}</span><strong>{{ \App\Support\OwnerMetricFormatter::rupiah($inventaris['ringkasan']['nilai_buku_akhir_tahun']) }}</strong>
            </div>
            <div><span>Belum
                    diperiksa</span><strong>{{ number_format($inventaris['dataBelumLengkap']['belum_diperiksa'], 0, ',', '.') }}</strong>
            </div>
        </div>
        <div class="owner-detail-grid">
            @include('owner.partials.chart', [
                'id' => 'ownerInventoryGrowthChart',
                'title' => 'Penambahan inventaris',
                'chart' => $inventaris['grafik'],
                'type' => 'bar',
            ])
            @include('owner.partials.distribution', [
                'title' => 'Kondisi inventaris',
                'items' => $inventaris['distribusi']['kondisi'],
            ])
        </div>
    </section>
@endif

@if ($kepegawaian)
    <section class="owner-module-section" aria-labelledby="ownerEmployeeTitle">
        <header class="owner-module-section__header">
            <div><span class="owner-module-section__icon"><i class="bi bi-people"></i></span></div>
            <div>
                <h2 id="ownerEmployeeTitle">Kepegawaian</h2>
                <p>Komposisi tenaga kerja dan pergerakannya tanpa identitas personal.</p>
            </div>
        </header>
        <div class="owner-fact-row">
            <div><span>Karyawan
                    aktif</span><strong>{{ number_format($kepegawaian['ringkasan']['aktif'], 0, ',', '.') }}</strong>
            </div>
            <div><span>Karyawan
                    nonaktif</span><strong>{{ number_format($kepegawaian['ringkasan']['nonaktif'], 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Kelengkapan data</span>
                @if ($kepegawaian['ringkasan']['kelengkapan_data']['disembunyikan'])
                    <strong class="owner-suppressed">Disembunyikan</strong>
                @else
                    <strong>{{ number_format((float) $kepegawaian['ringkasan']['kelengkapan_data']['nilai'], 1, ',', '.') }}%</strong>
                @endif
            </div>
        </div>
        <div class="owner-detail-grid">
            @include('owner.partials.chart', [
                'id' => 'ownerEmployeeMovementChart',
                'title' => 'Pergerakan karyawan',
                'chart' => $kepegawaian['grafik'],
            ])
            @include('owner.partials.distribution', [
                'title' => 'Status kepegawaian',
                'items' => $kepegawaian['distribusi']['status'],
            ])
        </div>
    </section>
@endif

@if ($absensi)
    <section class="owner-module-section" aria-labelledby="ownerAttendanceTitle">
        <header class="owner-module-section__header">
            <div><span class="owner-module-section__icon"><i class="bi bi-calendar-check"></i></span></div>
            <div>
                <h2 id="ownerAttendanceTitle">Absensi</h2>
                <p>Tren pencatatan kehadiran pada periode yang dipilih.</p>
            </div>
        </header>
        <div class="owner-fact-row">
            <div><span>Total
                    pencatatan</span><strong>{{ number_format($absensi['ringkasan']['total_pencatatan'], 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Tingkat kehadiran</span>
                @if ($absensi['ringkasan']['persentase_hadir']['disembunyikan'])
                    <strong class="owner-suppressed">Disembunyikan</strong>
                @else
                    <strong>{{ number_format((float) $absensi['ringkasan']['persentase_hadir']['nilai'], 1, ',', '.') }}%</strong>
                @endif
            </div>
        </div>
        @include('owner.partials.chart', [
            'id' => 'ownerAttendanceChart',
            'title' => 'Tren status kehadiran',
            'chart' => $absensi['grafik'],
        ])
    </section>
@endif

@if ($penggajian)
    <section class="owner-module-section" aria-labelledby="ownerPayrollTitle">
        <header class="owner-module-section__header">
            <div><span class="owner-module-section__icon"><i class="bi bi-cash-stack"></i></span></div>
            <div>
                <h2 id="ownerPayrollTitle">Penggajian</h2>
                <p>Total organisasi per periode; nilai dan penerima individual tidak tersedia.</p>
            </div>
        </header>
        <div class="owner-fact-row">
            <div><span>Total
                    transaksi</span><strong>{{ number_format($penggajian['ringkasan']['total_transaksi'], 0, ',', '.') }}</strong>
            </div>
            <div><span>Total gaji bersih</span><strong>{{ \App\Support\OwnerMetricFormatter::rupiah($penggajian['ringkasan']['total_gaji_bersih']) }}</strong>
            </div>
            <div><span>Total tunjangan</span><strong>{{ \App\Support\OwnerMetricFormatter::rupiah($penggajian['ringkasan']['total_tunjangan']) }}</strong>
            </div>
            <div>
                <span>Rata-rata gaji bersih</span>
                @if ($penggajian['ringkasan']['rata_rata_gaji_bersih']['disembunyikan'])
                    <strong class="owner-suppressed">Disembunyikan</strong>
                @else
                    <strong>{{ \App\Support\OwnerMetricFormatter::rupiah($penggajian['ringkasan']['rata_rata_gaji_bersih']['nilai']) }}</strong>
                @endif
            </div>
        </div>
        @include('owner.partials.chart', [
            'id' => 'ownerPayrollChart',
            'title' => 'Total gaji bersih bulanan',
            'chart' => $penggajian['grafik'],
        ])
    </section>
@endif
