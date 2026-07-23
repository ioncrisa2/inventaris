<!DOCTYPE html>
<html lang="id-ID">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistem Inventaris & Kepegawaian')</title>

    <script src="{{ Vite::asset('resources/js/theme.js') }}"></script>
    @vite('resources/css/app.css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="bg-body-tertiary">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 z-3 m-2 btn btn-light" href="#main-content">Lewati ke konten</a>

    <nav class="app-topbar-nav navbar navbar-expand-lg">
        <div class="container-fluid px-0">
            <a class="navbar-brand app-topbar-nav-brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('assets/img/logo-koperasi.png') }}" alt="Logo" class="sidebar-logo">
                <span>{{ config('app.name') }}</span>
            </a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#topbarNavCollapse"
                aria-controls="topbarNavCollapse"
                aria-expanded="false"
                aria-label="Buka menu"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="topbarNavCollapse">
                @include('partials.topbar-menu')
                @include('partials.user-dropdown', ['class' => 'mt-3 mt-lg-0'])
            </div>
        </div>
    </nav>

    <div class="app-shell">
        @include('partials.sidebar')

        <main class="app-main" id="main-content" tabindex="-1">
            <header class="app-topbar">
                <button
                    type="button"
                    class="btn btn-light border sidebar-desktop-toggle d-none d-lg-inline-flex"
                    id="sidebarToggle"
                    aria-label="Ciutkan atau lebarkan menu"
                    title="Ciutkan/lebarkan menu"
                >
                    <i class="bi bi-list"></i>
                </button>

                @include('partials.user-dropdown', ['class' => 'ms-auto'])
            </header>

            <div class="app-main-content">
                @yield('content')
            </div>
        </main>
    </div>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        <span id="confirmDeleteTitle">Konfirmasi Hapus</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmDeleteMessage" class="mb-0">Yakin ingin menghapus data ini? Tindakan ini tidak bisa dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="confirmDeleteCancel">Batal</button>
                    <form id="confirmDeleteForm" method="POST" action="#">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i>
                            Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmLogoutModal" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmLogoutModalLabel">Konfirmasi Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Yakin ingin keluar dari sistem?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i>
                            Ya, Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @vite('resources/js/app.js')
</body>

</html>
