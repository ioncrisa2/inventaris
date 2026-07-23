@props(['long' => false])

<footer class="app-footer" aria-label="Informasi aplikasi">
    <div class="app-footer__inner">
        <span class="app-footer__name">{{ config('app.name') }}</span>
        <span class="app-footer__separator" aria-hidden="true">&middot;</span>
        <span>Versi {{ config('app.version') }}</span>
        <span class="app-footer__separator" aria-hidden="true">&middot;</span>

        @if(filled(config('app.github_url')))
            <a
                class="app-footer__link"
                href="{{ config('app.github_url') }}"
                target="_blank"
                rel="noopener noreferrer">
                <i class="bi bi-github" aria-hidden="true"></i>
                GitHub
                <span class="visually-hidden">(dibuka di tab baru)</span>
            </a>
        @else
            <span class="app-footer__repository-pending" title="Isi APP_GITHUB_URL untuk mengaktifkan tautan repository">
                <i class="bi bi-github" aria-hidden="true"></i>
                Repository belum dihubungkan
            </span>
        @endif
    </div>
</footer>
