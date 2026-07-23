@props([
    'id',
    'title' => null,
    'action',
    'method' => 'POST',
    'formId' => null,
    'submitLabel' => 'Simpan',
    'submitVariant' => 'primary',
    'dialogClass' => null,
])

<div {{ $attributes->merge(['class' => 'modal fade']) }} id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog {{ $dialogClass }}">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" @if($formId) id="{{ $formId }}" @endif>
                @csrf
                @unless(strtoupper($method) === 'POST')
                @method($method)
                @endunless

                <div class="modal-header">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                    @endisset
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    {{ $slot }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-{{ $submitVariant }}">
                        <i class="bi bi-save"></i>
                        {{ $submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
