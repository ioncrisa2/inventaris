@extends('layouts.app')

@section('title', 'Detail Slip Gaji Saya')

@section('content')
<x-app-page>
    <x-page-header
        title="Slip Gaji {{ \Illuminate\Support\Carbon::create($transaksiGaji->tahun, $transaksiGaji->bulan, 1)->translatedFormat('F Y') }}"
        subtitle="{{ $karyawan->nama_lengkap }} — {{ $karyawan->nik }}"
    >
        <x-slot:actions><a class="btn btn-light" href="{{ route('me.salary-slips.index') }}">Kembali</a></x-slot:actions>
    </x-page-header>

    <div class="card content-narrow">
        <div class="card-body">
            <div class="salary-detail-breakdown">
                <section aria-labelledby="salary-income-heading">
                    <h2 class="salary-detail-breakdown__heading" id="salary-income-heading">Pendapatan</h2>
                    <dl class="salary-detail-breakdown__list">
                        <div>
                            <dt>Gaji Pokok</dt>
                            <dd>Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</dd>
                        </div>
                        @foreach($transaksiGaji->details->where('jenis_snapshot', 'Tunjangan') as $detail)
                            <div>
                                <dt>{{ $detail->nama_komponen_snapshot }}</dt>
                                <dd>Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="salary-detail-breakdown__subtotal salary-detail-breakdown__subtotal--gross">
                        <span>Total Gaji</span>
                        <strong>Rp {{ number_format($totalGaji, 0, ',', '.') }}</strong>
                    </div>
                </section>

                <section aria-labelledby="salary-deduction-heading">
                    <h2 class="salary-detail-breakdown__heading" id="salary-deduction-heading">Potongan</h2>
                    <dl class="salary-detail-breakdown__list">
                        @forelse($transaksiGaji->details->where('jenis_snapshot', 'Potongan') as $detail)
                            <div>
                                <dt>{{ $detail->nama_komponen_snapshot }}</dt>
                                <dd>- Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}</dd>
                            </div>
                        @empty
                            <div class="salary-detail-breakdown__empty">
                                <dt>Tidak ada potongan</dt>
                                <dd>Rp 0</dd>
                            </div>
                        @endforelse
                    </dl>
                    <div class="salary-detail-breakdown__subtotal">
                        <span>Total Potongan</span>
                        <strong class="text-danger">- Rp {{ number_format($totalPotongan, 0, ',', '.') }}</strong>
                    </div>
                </section>

                <div class="salary-detail-breakdown__net">
                    <span>Take Home Pay</span>
                    <strong>Rp {{ number_format($transaksiGaji->gaji_bersih, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
    </div>
</x-app-page>
@endsection
