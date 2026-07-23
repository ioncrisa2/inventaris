<div class="dropdown {{ $class ?? '' }}">
    <button type="button" class="app-topbar-user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="app-topbar-avatar">{{ auth()->user()->initials() }}</span>
        <span class="app-topbar-username">{{ auth()->user()->name }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item" href="{{ route('profile.show') }}">
                <i class="bi bi-person me-2"></i>Profil
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('pengaturan.edit') }}">
                <i class="bi bi-gear me-2"></i>Pengaturan Aplikasi
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#confirmLogoutModal">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </button>
        </li>
    </ul>
</div>
