@props(['title', 'items' => []])

<section class="owner-panel owner-distribution" aria-label="{{ $title }}">
    <header class="owner-panel__header">
        <h3>{{ $title }}</h3>
    </header>
    <div class="owner-panel__body">
        @php($total = collect($items)->sum(fn($item) => (int) ($item['total'] ?? 0)))
        @forelse($items as $item)
            <div class="owner-distribution__row">
                <div>
                    <span>{{ $item['label'] }}</span>
                    @if ($item['disembunyikan'] ?? false)
                        <small>{{ $item['pesan'] }}</small>
                    @endif
                </div>
                @if (!($item['disembunyikan'] ?? false))
                    <strong>{{ number_format($item['total'], 0, ',', '.') }}</strong>
                @else
                    <i class="bi bi-shield-lock" aria-label="Nilai disembunyikan"></i>
                @endif
            </div>
            @if (!($item['disembunyikan'] ?? false) && $total > 0)
                <div class="progress owner-distribution__progress" role="progressbar" aria-label="{{ $item['label'] }}"
                    aria-valuenow="{{ $item['total'] }}" aria-valuemin="0" aria-valuemax="{{ $total }}">
                    <div class="progress-bar" style="width: {{ min(100, ($item['total'] / $total) * 100) }}%"></div>
                </div>
            @endif
        @empty
            <p class="owner-muted-empty">Belum ada distribusi yang dapat ditampilkan.</p>
        @endforelse
    </div>
</section>
