@extends('layouts.app')

@section('title', 'Activity Log - System Owner')

@section('content')
<x-app-page long-footer>
    <x-page-header
        title="Activity Log"
        subtitle="Jejak akses dan perubahan yang dilakukan akun System Owner pada control plane platform."
    />

    <x-data-table
        :paginator="$logs"
        title="Aktivitas System Owner"
        subtitle="Log terbaru ditampilkan lebih dahulu. Isi formulir dan payload sensitif tidak direkam."
    >
        <x-slot:toolbar>
            <x-filter-form
                :action="route('owner.activity-logs.index')"
                :reset-route="route('owner.activity-logs.index')"
                :has-filters="request()->hasAny(['actor_user_id', 'koperasi_id', 'action', 'method', 'response_status', 'date_from', 'date_to'])"
            >
                <div class="col-12 col-md-auto">
                    <label class="visually-hidden" for="activity_actor">Aktor</label>
                    <select class="form-select" id="activity_actor" name="actor_user_id">
                        <option value="">Semua owner</option>
                        @foreach($owners as $owner)
                            <option value="{{ $owner->id }}" @selected((string) request('actor_user_id') === (string) $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <label class="visually-hidden" for="activity_koperasi">Koperasi</label>
                    <select class="form-select" id="activity_koperasi" name="koperasi_id">
                        <option value="">Semua koperasi</option>
                        @foreach($koperasis as $koperasi)
                            <option value="{{ $koperasi->id }}" @selected((string) request('koperasi_id') === (string) $koperasi->id)>{{ $koperasi->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <label class="visually-hidden" for="activity_action">Nama aksi</label>
                    <input class="form-control" id="activity_action" name="action" type="search" maxlength="100"
                        value="{{ request('action') }}" placeholder="Cari aksi…">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="visually-hidden" for="activity_method">Metode</label>
                    <select class="form-select" id="activity_method" name="method">
                        <option value="">Semua metode</option>
                        @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                            <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label class="visually-hidden" for="activity_status">Status respons</label>
                    <input class="form-control" id="activity_status" name="response_status" type="number" min="100" max="599"
                        value="{{ request('response_status') }}" placeholder="Status HTTP">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="visually-hidden" for="activity_from">Dari tanggal</label>
                    <input class="form-control" id="activity_from" name="date_from" type="date" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="visually-hidden" for="activity_to">Sampai tanggal</label>
                    <input class="form-control" id="activity_to" name="date_to" type="date" value="{{ request('date_to') }}">
                </div>
            </x-filter-form>
        </x-slot:toolbar>

        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Aktor</th>
                    <th scope="col">Aksi</th>
                    <th scope="col">Target</th>
                    <th scope="col">Status</th>
                    <th scope="col">Konteks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $statusTone = $log->response_status >= 500
                            ? 'text-bg-danger'
                            : ($log->response_status >= 400 ? 'text-bg-warning' : 'text-bg-success');
                    @endphp
                    <tr>
                        <td data-label="Waktu">
                            <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->locale('id')->translatedFormat('d M Y, H:i:s') }}</time>
                        </td>
                        <td data-label="Aktor">
                            <strong>{{ $log->actor?->name ?? 'User telah dihapus' }}</strong>
                            @if($log->actor)<div class="small text-body-secondary">{{ $log->actor->email }}</div>@endif
                        </td>
                        <td data-label="Aksi">
                            <code>{{ $log->action }}</code>
                            <div class="small text-body-secondary">{{ $log->route }}</div>
                        </td>
                        <td data-label="Target">{{ $log->koperasi?->nama ?? 'Platform' }}</td>
                        <td data-label="Status"><span class="badge {{ $statusTone }}">{{ $log->response_status }}</span></td>
                        <td data-label="Konteks">
                            <div>{{ $log->ip_address ?? 'IP tidak tersedia' }}</div>
                            @if($log->filters)
                                <div class="small text-body-secondary text-break">
                                    {{ collect($log->filters)->map(fn ($value, $key) => $key.'='.$value)->implode(' · ') }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-empty-row :colspan="6">
                        {{ request()->hasAny(['actor_user_id', 'koperasi_id', 'action', 'method', 'response_status', 'date_from', 'date_to'])
                            ? 'Tidak ada aktivitas yang cocok dengan filter.'
                            : 'Belum ada aktivitas System Owner yang tercatat.' }}
                    </x-empty-row>
                @endforelse
            </tbody>
        </table>
    </x-data-table>
</x-app-page>
@endsection
