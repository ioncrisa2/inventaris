<div class="salary-slip__payroll">
    <div class="salary-slip__base">
        <span>Gaji Pokok</span>
        <strong>Rp {{ number_format($transaksi->gaji_pokok, 0, ',', '.') }}</strong>
    </div>

    <div class="salary-slip__components salary-slip__components--{{ $block['variant'] }}">
        <section class="salary-slip__component-group salary-slip__component-group--income">
            <header>
                <h3>Tunjangan</h3>
                <strong>+ Rp {{ number_format($slip['total_tunjangan'], 0, ',', '.') }}</strong>
            </header>
            <div class="salary-slip__component-list">
                @forelse($slip['tunjangan'] as $detail)
                    <div class="salary-slip__component-row">
                        <span>{{ $detail->nama_komponen_snapshot }}</span>
                        <strong>Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <p class="salary-slip__empty-row">Tidak ada tunjangan</p>
                @endforelse
            </div>
        </section>

        <div class="salary-slip__gross">
            <span>Total Gaji</span>
            <strong>Rp {{ number_format($slip['total_gaji'], 0, ',', '.') }}</strong>
        </div>

        <section class="salary-slip__component-group salary-slip__component-group--deduction">
            <header>
                <h3>Potongan</h3>
                <strong>− Rp {{ number_format($slip['total_potongan'], 0, ',', '.') }}</strong>
            </header>
            <div class="salary-slip__component-list">
                @forelse($slip['potongan'] as $detail)
                    <div class="salary-slip__component-row">
                        <span>{{ $detail->nama_komponen_snapshot }}</span>
                        <strong>Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <p class="salary-slip__empty-row">Tidak ada potongan</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="salary-slip__net">
        <span>Take Home Pay</span>
        <strong>Rp {{ number_format($transaksi->gaji_bersih, 0, ',', '.') }}</strong>
    </div>
</div>
