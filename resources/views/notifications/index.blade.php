@extends('layouts.app')

@section('title', 'Notifikasi - Sistem Inventaris & Kepegawaian')

@section('content')
    <x-app-page width="narrow">
        <x-page-header title="Notifikasi" subtitle="Pembaruan ringkas dari pusat request produk.">
            @if(auth()->user()->unreadNotifications()->exists())
                <x-slot:actions>
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-check2-all" aria-hidden="true"></i> Tandai semua dibaca
                        </button>
                    </form>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-flash-alert />

        <x-section-card flush>
            @forelse($notifications as $notification)
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button class="notification-row {{ $notification->read_at ? '' : 'notification-row--unread' }}" type="submit">
                        <span class="notification-row__icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i></span>
                        <span class="notification-row__body">
                            <strong>{{ $notification->data['title'] ?? \App\Notifications\ProductRequestUpdated::eventLabel($notification->data['event'] ?? '') }}</strong>
                            <span>{{ $notification->data['body'] ?? (($notification->data['ticket_number'] ?? 'Request produk').' · '.($notification->data['status_label'] ?? 'Diperbarui')) }}</span>
                        </span>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->locale('id')->diffForHumans() }}</time>
                    </button>
                </form>
            @empty
                <x-empty-state icon="bi-bell" title="Belum ada notifikasi" class="m-4">
                    Pembaruan request produk akan muncul di sini.
                </x-empty-state>
            @endforelse

            @if($notifications->hasPages())
                <div class="p-3 border-top">{{ $notifications->links() }}</div>
            @endif
        </x-section-card>
    </x-app-page>
@endsection
