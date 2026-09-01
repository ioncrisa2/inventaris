<tr>
    <td>{{ $detail->nama_komponen_snapshot }}</td>
    <td>
        <x-badge :color="$detail->jenis_snapshot === 'Tunjangan' ? 'text-bg-success' : 'text-bg-secondary'">{{ $detail->jenis_snapshot }}</x-badge>
    </td>
    <td>{{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$detail->metode_perhitungan_snapshot] ?? $detail->metode_perhitungan_snapshot }}</td>
    <td class="text-end">
        @if($detail->metode_perhitungan_snapshot === 'persentase')
            {{ rtrim(rtrim($detail->nilai_snapshot, '0'), '.') }}%
        @elseif($detail->metode_perhitungan_snapshot === 'persentase_pengali')
            {{ rtrim(rtrim($detail->nilai_snapshot, '0'), '.') }}% &times; {{ $detail->jumlah_pengali_snapshot ?? 0 }}
        @elseif($detail->metode_perhitungan_snapshot === 'per_hari')
            Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari &times; {{ $detail->jumlah_hari_snapshot ?? 0 }} hari Hadir
            @if($detail->tanggal_awal_snapshot && $detail->tanggal_akhir_snapshot)
                <span class="text-body-secondary small d-block">Periode {{ $detail->tanggal_awal_snapshot->format('d/m/Y') }} s.d. {{ $detail->tanggal_akhir_snapshot->format('d/m/Y') }}</span>
            @endif
        @elseif($detail->metode_perhitungan_snapshot === 'harian_sehari')
            Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari
            @if($detail->tanggal_awal_snapshot)
                <span class="text-body-secondary small d-block">{{ $detail->tanggal_awal_snapshot->format('d/m/Y') }}</span>
            @endif
        @elseif($detail->metode_perhitungan_snapshot === 'harian_manual')
            Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }} /hari &times; {{ $detail->jumlah_hari_snapshot ?? 0 }} hari
        @else
            Rp {{ number_format($detail->nilai_snapshot, 0, ',', '.') }}
        @endif
    </td>
    <td class="text-end {{ $detail->jenis_snapshot === 'Tunjangan' ? 'text-success' : 'text-danger' }}">
        {{ $detail->jenis_snapshot === 'Tunjangan' ? '+' : '-' }} Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}
    </td>
</tr>
