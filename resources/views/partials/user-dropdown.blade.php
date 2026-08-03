<div class="dropdown {{ $class ?? '' }}">
    <button type="button" class="app-topbar-user dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <span class="app-topbar-avatar">{{ auth()->user()->initials() }}</span>
        <span class="app-topbar-username">{{ auth()->user()->name }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end user-menu-panel">
        <div class="user-menu-header">
            <span class="app-topbar-avatar">{{ auth()->user()->initials() }}</span>
            <div class="user-menu-header__body">
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ auth()->user()->email }}</small>
            </div>
        </div>

        <div class="user-menu-section">
            <div class="user-menu-icon-group" role="group" aria-label="Pilih tema warna">
                <button type="button" class="user-menu-icon-btn" data-color-mode-option="light" title="Terang" aria-label="Tema terang">
                    <i class="bi bi-sun" aria-hidden="true"></i>
                </button>
                <button type="button" class="user-menu-icon-btn" data-color-mode-option="dark" title="Gelap" aria-label="Tema gelap">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                </button>
                <button type="button" class="user-menu-icon-btn" data-color-mode-option="auto" title="Sistem" aria-label="Ikuti tema sistem">
                    <i class="bi bi-display" aria-hidden="true"></i>
                </button>
            </div>

            <div class="user-menu-icon-group" role="group" aria-label="Pilih tampilan menu">
                <button type="button" class="user-menu-icon-btn" data-app-layout-option="sidebar" title="Sidebar" aria-label="Tampilan sidebar">
                    <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
                </button>
                <button type="button" class="user-menu-icon-btn" data-app-layout-option="topbar" title="Top Bar" aria-label="Tampilan top bar">
                    <i class="bi bi-layout-text-window" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <hr class="dropdown-divider">

        <a class="dropdown-item" href="{{ route('profile.show') }}">
            <i class="bi bi-person me-2"></i>Profil
        </a>
        <a class="dropdown-item" href="{{ route('pengaturan.edit') }}">
            <i class="bi bi-gear me-2"></i>Pengaturan Aplikasi
        </a>

        <hr class="dropdown-divider">

        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#confirmLogoutModal">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </button>
    </div>
</div>
