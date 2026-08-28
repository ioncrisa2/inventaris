@extends('layouts.app')

@section('title', 'Request Produk - Sistem Inventaris & Kepegawaian')

@section('content')
    <x-app-page>
        <x-page-header title="Request Produk" subtitle="Ajukan kebutuhan, pantau tindak lanjut, dan lanjutkan percakapan dengan tim produk.">
            <x-slot:actions>
                @can('create', \App\Models\ProductRequest::class)
                    <a class="btn btn-primary" href="{{ route('product-requests.create') }}">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i> Ajukan request
                    </a>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$productRequests" title="Daftar request" subtitle="Diurutkan berdasarkan aktivitas publik terbaru.">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('product-requests.index')"
                    :reset-route="route('product-requests.index')"
                    :has-filters="request()->hasAny(['search', 'type', 'status'])"
                >
                    <div class="col-12 col-lg-auto">
                        <label class="visually-hidden" for="request_search">Cari request</label>
                        <input class="form-control" id="request_search" name="search" type="search"
                            value="{{ request('search') }}" placeholder="Cari tiket atau judul…">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="request_type">Jenis</label>
                        <select class="form-select" id="request_type" name="type">
                            <option value="">Semua jenis</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="request_status">Status</label>
                        <select class="form-select" id="request_status" name="status">
                            <option value="">Semua status</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Tiket</th>
                        <th scope="col">Request</th>
                        <th scope="col">Jenis</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aktivitas terakhir</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productRequests as $item)
                        <tr>
                            <td data-label="Tiket">
                                <a class="request-ticket-link" href="{{ route('product-requests.show', $item->ticket_number) }}">
                                    {{ $item->ticket_number }}
                                </a>
                            </td>
                            <td data-label="Request">
                                <strong>{{ $item->title }}</strong>
                                @if(auth()->user()->isAdminPrimer() && $item->creator)
                                    <div class="small text-body-secondary">Diajukan oleh {{ $item->creator->name }}</div>
                                @endif
                            </td>
                            <td data-label="Jenis">{{ $item->type->label() }}</td>
                            <td data-label="Status">
                                <span class="product-request-status product-request-status--{{ $item->status->tone() }}">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td data-label="Aktivitas terakhir">
                                <span title="{{ $item->last_activity_at->locale('id')->translatedFormat('d F Y, H:i') }}">
                                    {{ $item->last_activity_at->locale('id')->diffForHumans() }}
                                </span>
                            </td>
                            <td class="text-end" data-label="Aksi">
                                <a class="btn btn-sm btn-action btn-action-neutral"
                                    href="{{ route('product-requests.show', $item->ticket_number) }}"
                                    aria-label="Buka {{ $item->ticket_number }}" title="Buka detail">
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row colspan="6">
                            @if(request()->hasAny(['search', 'type', 'status']))
                                Tidak ada request yang cocok dengan filter. <a href="{{ route('product-requests.index') }}">Hapus filter</a>.
                            @else
                                Belum ada request produk.
                                @can('create', \App\Models\ProductRequest::class)
                                    <a href="{{ route('product-requests.create') }}">Ajukan request pertama</a>.
                                @endcan
                            @endif
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>
@endsection
