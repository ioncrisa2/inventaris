@props([
    'tabs',
    'label' => 'Bagian laporan',
])

<div {{ $attributes->class(['card report-tabs-card']) }} data-report-tabs>
    <div class="card-body p-3 p-lg-4">
        <div class="row g-4 report-tabs__layout">
            <aside class="col-lg-4 col-xl-3 report-tabs__sidebar">
                <nav
                    class="report-tabs__nav"
                    role="tablist"
                    aria-label="{{ $label }}"
                    aria-orientation="vertical"
                >
                    <div class="settings-nav__label">{{ $label }}</div>

                    @foreach($tabs as $id => $tab)
                        <button
                            class="settings-nav__link report-tabs__link {{ $loop->first ? 'active' : '' }}"
                            id="{{ $id }}-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#{{ $id }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $id }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        >
                            <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>
                            <span>
                                <strong>{{ $tab['label'] }}</strong>
                                @if(!empty($tab['description']))
                                    <small>{{ $tab['description'] }}</small>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            <div class="col-lg-8 col-xl-9">
                <div class="tab-content report-tabs__content">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
