@props([
    'id',
    'noun' => 'data',
    'deleteAction' => null,
    'deleteMessage' => 'Data yang dihapus tidak dapat dipulihkan.',
])

@php
    $modalId = 'bulkDeleteModal-'.\Illuminate\Support\Str::slug($id);
@endphp

<div class="bulk-action-bar" data-bulk-action-bar="{{ $id }}" hidden>
    <div class="bulk-action-bar__form">
        <div class="bulk-action-bar__status" aria-live="polite">
            <i class="bi bi-check2-square" aria-hidden="true"></i>
            <span><strong data-bulk-count>0</strong> {{ $noun }} dipilih</span>
            <span class="bulk-action-bar__blocked" data-bulk-blocked-reason hidden></span>
        </div>

        <div class="bulk-action-bar__actions">
            <button class="btn btn-sm btn-light" type="button" data-bulk-clear>
                Batalkan pilihan
            </button>

            @isset($actions)
                {{ $actions }}
            @endisset

            @if($deleteAction)
                <button
                    class="btn btn-sm btn-danger"
                    type="button"
                    data-bulk-delete-trigger
                    data-bs-toggle="modal"
                    data-bs-target="#{{ $modalId }}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    Hapus Terpilih
                </button>
            @endif
        </div>
    </div>
</div>

@if($deleteAction)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-bulk-delete-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $modalId }}Label">Hapus <span data-bulk-modal-count>0</span> {{ $noun }}?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">{{ $deleteMessage }}</p>
                <p class="mb-0 text-danger fw-semibold">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <form
                    method="POST"
                    action="{{ $deleteAction }}"
                    data-bulk-form="{{ $id }}"
                    data-bulk-input-name="ids[]">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@once
@endonce
