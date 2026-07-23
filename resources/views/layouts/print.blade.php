<!DOCTYPE html>
<html lang="id-ID">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Laporan')</title>
    @vite('resources/css/print.css')
</head>

<body class="page-layout--@yield('print_layout', 'a4-landscape')">
    <div class="screen-actions no-print">
        @yield('screen_actions')
        <a href="@yield('back_url', url()->previous())">Kembali</a>
        <button type="button" data-print-page>Cetak</button>
    </div>

    <div class="no-print">
        @yield('screen_settings')
    </div>

    <main class="print-page">
        @yield('content')
    </main>
    @vite('resources/js/print.js')
</body>

</html>
