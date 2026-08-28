@extends('layouts.app')

@section('title', 'Inbox Request Produk - System Owner')

@section('content')
    @php
        $formatDuration = static function ($minutes): string {
            if ($minutes === null) return 'Belum tersedia';
            if ($minutes < 60) return number_format($minutes, 0, ',', '.').' menit';
            if ($minutes < 1440) return number_format($minutes / 60, 1, ',', '.').' jam';
            return number_format($minutes / 1440, 1, ',', '.').' hari';
        };
    @endphp

    <x-app-page long-footer>
        <x-page-header title="Inbox Request Produk"
            subtitle="Triase masukan lintas koperasi tanpa membuka data operasional lain di luar isi yang dikirim pengguna." />

        <section class="request-stat-strip" aria-label="Statistik request terfilter">
            <article>
                <span><i class="bi bi-inboxes" aria-hidden="true"></i></span>
                <div><p>Total</p><strong>{{ number_format($statistics['total'], 0, ',', '.') }}</strong></div>
            </article>
            <article>
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                <div><p>Backlog aktif</p><strong>{{ number_format($statistics['backlog'], 0, ',', '.') }}</strong></div>
            </article>
            <article>
                <span><i class="bi bi-chat-left-dots" aria-hidden="true"></i></span>
                <div><p>Respons pertama</p><strong>{{ $formatDuration($statistics['average_first_response_minutes']) }}</strong></div>
            </article>
            <article>
                <span><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                <div><p>Waktu penyelesaian</p><strong>{{ $formatDuration($statistics['average_resolution_minutes']) }}</strong></div>
            </article>
        </section>

        <x-data-table :paginator="$productRequests" title="Antrian request" subtitle="Aktivitas publik terbaru berada di urutan pertama." class="mt-4">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('owner.product-requests.index')"
                    :reset-route="route('owner.product-requests.index')"
                    :has-filters="request()->hasAny(['search', 'koperasi_id', 'type', 'status', 'priority', 'assigned_to', 'date_from', 'date_to'])"
                >
                    <div class="col-12 col-xl-auto">
                        <label class="visually-hidden" for="owner_request_search">Cari request</label>
                        <input class="form-control" id="owner_request_search" name="search" type="search"
                            value="{{ request('search') }}" placeholder="Tiket atau judul…">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_koperasi">Koperasi</label>
                        <select class="form-select" id="owner_request_koperasi" name="koperasi_id">
                            <option value="">Semua koperasi</option>
                            @foreach($koperasis as $koperasi)
                                <option value="{{ $koperasi->id }}" @selected((string) request('koperasi_id') === (string) $koperasi->id)>{{ $koperasi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_type">Jenis</label>
                        <select class="form-select" id="owner_request_type" name="type">
                            <option value="">Semua jenis</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_status">Status</label>
                        <select class="form-select" id="owner_request_status" name="status">
                            <option value="">Semua status</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_priority">Prioritas internal</label>
                        <select class="form-select" id="owner_request_priority" name="priority">
                            <option value="">Semua prioritas</option>
                            @foreach($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_assignee">Penanggung jawab</label>
                        <select class="form-select" id="owner_request_assignee" name="assigned_to">
                            <option value="">Semua owner</option>
                            @foreach($owners as $owner)
                                <option value="{{ $owner->id }}" @selected((string) request('assigned_to') === (string) $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_from">Dari tanggal</label>
                        <input class="form-control" id="owner_request_from" name="date_from" type="date" value="{{ request('date_from') }}" title="Dari tanggal">
                    </div>
                    <div class="col-6 col-lg-auto">
                        <label class="visually-hidden" for="owner_request_to">Sampai tanggal</label>
                        <input class="form-control" id="owner_request_to" name="date_to" type="date" value="{{ request('date_to') }}" title="Sampai tanggal">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Tiket / request</th>
                        <th scope="col">Koperasi</th>
                        <th scope="col">Jenis</th>
                        <th scope="col">Status</th>
                        <th scope="col">Prioritas</th>
                        <th scope="col">Penanggung jawab</th>
                        <th scope="col">Aktivitas</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productRequests as $item)
                        <tr>
                            <td data-label="Request">
                                <a class="request-ticket-link" href="{{ route('owner.product-requests.show', $item->ticket_number) }}">{{ $item->ticket_number }}</a>
                                <div><strong>{{ $item->title }}</strong></div>
                            </td>
                            <td data-label="Koperasi">{{ $item->koperasi->nama }}</td>
                            <td data-label="Jenis">{{ $item->type->label() }}</td>
                            <td data-label="Status">
                                <span class="product-request-status product-request-status--{{ $item->status->tone() }}">{{ $item->status->label() }}</span>
                            </td>
                            <td data-label="Prioritas">{{ $item->internal_priority?->label() ?? 'Belum dinilai' }}</td>
                            <td data-label="Penanggung jawab">{{ $item->assignedOwner?->name ?? 'Belum ditugaskan' }}</td>
                            <td data-label="Aktivitas">{{ $item->last_activity_at->locale('id')->diffForHumans() }}</td>
                            <td class="text-end" data-label="Aksi">
                                <a class="btn btn-sm btn-action btn-action-neutral" href="{{ route('owner.product-requests.show', $item->ticket_number) }}" aria-label="Triase {{ $item->ticket_number }}">
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-row colspan="8">
                            {{ request()->hasAny(['search', 'koperasi_id', 'type', 'status', 'priority', 'assigned_to', 'date_from', 'date_to']) ? 'Tidak ada request yang cocok dengan filter.' : 'Belum ada request produk dari tenant.' }}
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>
@endsection
