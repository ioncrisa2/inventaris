@props(['user'])

@if (is_null($user->dashboard_banner_dismissed_at))
    <aside class="dashboard-tip" aria-label="Tips penggunaan">
        <p class="dashboard-tip__message">
            <span class="dashboard-tip__copy">Selamat datang — kelola data Anda di sini.</span>
            <a href="{{ route('panduan-singkat') }}">Panduan singkat</a>
        </p>
        <form method="POST" action="{{ route('dashboard.banner.dismiss') }}">
            @csrf
            @method('PATCH')
            @if (request()->filled('periode'))
                <input type="hidden" name="periode" value="{{ request('periode') }}">
            @endif
            <button type="submit" class="btn-close dashboard-tip__dismiss"
                aria-label="Jangan tampilkan lagi tips penggunaan" title="Jangan tampilkan lagi"></button>
        </form>
    </aside>
@endif
