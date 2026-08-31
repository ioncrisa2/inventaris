@props([
    'id',
    'title',
    'items' => [],
    'icon' => 'bi-shield-check',
    'interactive' => true,
])

{{--
    Setiap notice harus membahas satu concern saja, misalnya lingkup akses
    ATAU keamanan akun. Jangan mencampur dua topik dalam satu instance.

    Setiap item memakai tepat satu `emphasis` agar batas/aksi penting mudah
    dipindai dan pola penekanannya konsisten untuk seluruh role.
--}}
<aside
    {{ $attributes->class(['role-access-notice']) }}
    aria-labelledby="{{ $id }}-title"
    @if($interactive)
        data-role-access-notice
        data-notice-key="role-access-notice.{{ auth()->id() }}.{{ $id }}"
    @endif
>
    <div class="role-access-notice__header" @if($interactive) data-role-access-notice-header @endif>
        <div class="role-access-notice__heading">
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
        </div>

        @if($interactive)
            <button
                class="role-access-notice__toggle"
                type="button"
                aria-expanded="true"
                aria-controls="{{ $id }}-content"
                aria-label="Tampilkan atau sembunyikan {{ mb_strtolower($title) }}"
                data-role-access-notice-toggle
            >
                <i class="bi bi-chevron-up role-access-notice__chevron" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    <div
        id="{{ $id }}-content"
        class="role-access-notice__content"
        @if($interactive) data-role-access-notice-content @endif
    >
        <ul>
            @foreach($items as $item)
                <li>
                    {{ $item['before'] ?? '' }}<strong>{{ $item['emphasis'] }}</strong>{{ $item['after'] ?? '' }}
                </li>
            @endforeach
        </ul>

        @if($interactive)
            <button class="role-access-notice__dismiss" type="button" data-role-access-notice-dismiss>
                <i class="bi bi-eye-slash" aria-hidden="true"></i>
                Jangan tampilkan lagi
            </button>
        @endif
    </div>

    @if($interactive)
        <button class="role-access-notice__restore" type="button" data-role-access-notice-restore hidden>
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
            Tampilkan {{ mb_strtolower($title) }}
        </button>
    @endif
</aside>
