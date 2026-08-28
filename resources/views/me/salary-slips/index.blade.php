@extends('layouts.app')

@section('title', 'Slip Gaji Saya')

@section('content')
<x-app-page>
    <x-page-header title="Slip Gaji Saya" subtitle="Slip yang sudah diterbitkan untuk {{ $karyawan->nama_lengkap }}." />
    <x-flash-alert />

    <x-data-table :paginator="$slips">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Periode</th><th>Diterbitkan</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                @forelse($slips as $slip)
                    <tr>
                        <td><strong>{{ \Illuminate\Support\Carbon::create($slip->tahun, $slip->bulan, 1)->translatedFormat('F Y') }}</strong></td>
                        <td>{{ $slip->published_at->translatedFormat('d F Y H:i') }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('me.salary-slips.show', $slip) }}">Lihat Slip</a></td>
                    </tr>
                @empty
                    <x-empty-row :colspan="3">Belum ada slip gaji yang diterbitkan.</x-empty-row>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-page>
@endsection
