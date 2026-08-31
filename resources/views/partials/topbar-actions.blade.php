@php
    $user = auth()->user();
    if (! request()->attributes->has('unread_notification_count')) {
        request()->attributes->set('unread_notification_count', $user->unreadNotifications()->count());
    }
    $unreadNotificationCount = (int) request()->attributes->get('unread_notification_count');
    $notificationLabel = $unreadNotificationCount > 0
        ? 'Notifikasi, '.$unreadNotificationCount.' belum dibaca'
        : 'Notifikasi';
@endphp

<div class="app-topbar-actions" data-topbar-actions>
    <a
        class="app-topbar-action {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}"
        href="{{ route('notifications.index') }}"
        aria-label="{{ $notificationLabel }}"
        title="{{ $notificationLabel }}"
        data-topbar-notifications
    >
        <i class="bi {{ $unreadNotificationCount > 0 ? 'bi-bell-fill' : 'bi-bell' }}" aria-hidden="true"></i>
        @if($unreadNotificationCount > 0)
            <span class="app-topbar-notification-badge" aria-hidden="true">
                {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
            </span>
        @endif
    </a>

    <button
        type="button"
        class="app-theme-switch"
        role="switch"
        aria-checked="false"
        aria-label="Aktifkan tema gelap"
        title="Aktifkan tema gelap"
        data-theme-switch
    >
        <span class="app-theme-switch__icon app-theme-switch__icon--light" aria-hidden="true">
            <i class="bi bi-sun-fill"></i>
        </span>
        <span class="app-theme-switch__thumb" aria-hidden="true"></span>
        <span class="app-theme-switch__icon app-theme-switch__icon--dark" aria-hidden="true">
            <i class="bi bi-moon-stars-fill"></i>
        </span>
    </button>

    @include('partials.user-dropdown')
</div>
