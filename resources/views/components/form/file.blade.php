@props([
    'name',
    'id' => null,
    'label' => null,
    'required' => false,
    'policy',
    'multiple' => false,
    'tenantId' => null,
    'showHelp' => true,
])

@php
    $uploadPolicy = \App\Support\UploadPolicy::get($policy);
    $clientConfig = \App\Support\UploadPolicy::clientConfig($policy);
    $inputId = $id ?: preg_replace('/[^A-Za-z0-9_-]+/', '-', $name);
    $errorKey = $multiple ? $name.'.*' : $name;

    if (! $multiple) {
        $clientConfig['maxFiles'] = 1;
        $clientConfig['maxTotalBytes'] = min($clientConfig['maxTotalBytes'], $clientConfig['maxFileBytes']);
    }

    $clientConfig['asyncEnabled'] = (bool) config('uploads.features.async', false)
        && auth()->check()
        && (auth()->user()->koperasi_id !== null || ($tenantId && auth()->user()->isSystemOwner()));
    $clientConfig['targetTenantId'] = $tenantId ? (int) $tenantId : null;
    $clientConfig['asyncStoreUrl'] = route('uploads.store');
    $clientConfig['asyncStatusUrl'] = route('uploads.show', ['storedFile' => '__UUID__']);
    $clientConfig['asyncDeleteUrl'] = route('uploads.destroy', ['storedFile' => '__UUID__']);
    $clientConfig['maxParallelUploads'] = (int) config('uploads.max_parallel_uploads', 3);
@endphp

{{-- Input file native tetap menjadi fallback saat JavaScript tidak tersedia. --}}
<div class="file-upload" data-file-upload data-upload-policy='@json($clientConfig)'>
    @if($label)
    <label for="{{ $inputId }}" class="form-label">
        {{ $label }}
        @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>
    @endif

    <div class="file-picker {{ $errors->has($errorKey) || $errors->has($name) ? 'is-invalid' : '' }}" data-file-picker>
        <input
            type="file"
            name="{{ $multiple ? $name.'[]' : $name }}"
            id="{{ $inputId }}"
            accept="{{ \App\Support\UploadPolicy::accept($policy) }}"
            @if($required) required @endif
            @if($multiple) multiple @endif
            {{ $attributes->class(['file-picker__input'])->merge([
                'data-file-upload-input' => '',
                'data-upload-policy-name' => $policy,
            ]) }}
        >

        <div class="file-picker__actions">
            <label class="btn btn-light file-picker__button" for="{{ $inputId }}">
                <i class="bi bi-upload" aria-hidden="true"></i>
                Pilih file
            </label>

            @if($uploadPolicy['camera'])
            <button type="button" class="btn btn-light file-picker__camera" data-file-upload-camera hidden>
                <i class="bi bi-camera" aria-hidden="true"></i>
                Ambil foto
            </button>
            @endif
        </div>

        <span class="file-picker__status" data-file-picker-status role="status" aria-live="polite">
            Belum ada file dipilih
        </span>
    </div>

    <div class="file-upload__summary" data-file-upload-summary hidden></div>
    <div class="file-upload__list" data-file-upload-list role="list" aria-label="File yang dipilih"></div>

    @if($errors->has($errorKey) || $errors->has($name))
    <div class="invalid-feedback d-block">{{ $errors->first($errorKey) ?: $errors->first($name) }}</div>
    @endif

    @if($showHelp)
    <div class="form-text">{{ $uploadPolicy['help'] }}</div>
    @endif
</div>
