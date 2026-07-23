<button class="btn btn-light border shadow-sm sidebar-mobile-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Buka menu">
    <i class="bi bi-list"></i>
</button>

<aside class="app-sidebar d-none d-lg-flex" aria-label="Sidebar utama">
    <div class="sidebar-inner">
        <a class="sidebar-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('assets/img/logo-koperasi.png') }}" alt="Logo" class="sidebar-logo">
            <span>{{ config('app.name') }}</span>
        </a>

        @include('partials.sidebar-menu', ['idPrefix' => 'desktop'])
    </div>
</aside>

<div class="offcanvas offcanvas-start app-sidebar-offcanvas d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header sidebar-offcanvas-header">
        <a class="sidebar-brand mb-0" href="{{ route('dashboard') }}" id="mobileSidebarLabel">
            <img src="{{ asset('assets/img/logo-koperasi.png') }}" alt="Logo" class="sidebar-logo">
            <span>{{ config('app.name') }}</span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
    </div>

    <div class="offcanvas-body sidebar-offcanvas-body">
        @include('partials.sidebar-menu', ['idPrefix' => 'mobile'])
    </div>
</div>

