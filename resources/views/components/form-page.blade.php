@props([
    'title',
    'subtitle' => null,
    'action',
    'method' => 'POST',
    'cancelRoute',
    'submitLabel' => 'Simpan',
])

<x-app-page>
        <div {{ $attributes->merge(['class' => 'form-page mx-auto']) }}>
            <header class="form-header mb-4">
                <h1>{{ $title }}</h1>
                @if($subtitle)
                <p>{{ $subtitle }}</p>
                @endif
            </header>

            @isset($top)
            {{ $top }}
            @endisset

            <div class="card form-card">
                <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
                    @csrf
                    @unless(strtoupper($method) === 'POST')
                    @method($method)
                    @endunless

                    <div class="card-body">
                        {{ $slot }}
                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2 bg-body-tertiary">
                        <a href="{{ $cancelRoute }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            {{ $submitLabel }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
</x-app-page>
