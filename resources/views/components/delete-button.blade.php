@props(['url', 'message', 'label' => 'Hapus', 'blockedMessage' => null])

@php
    // Beberapa pemanggil lama memakai &quot; di string pesan. Decode satu kali
    // sebelum Blade melakukan escaping atribut agar dataset DOM menerima tanda
    // kutip normal tanpa membuka celah injeksi HTML.
    $deleteMessage = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $deleteBlockedMessage = $blockedMessage
        ? html_entity_decode($blockedMessage, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        : null;
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => 'btn btn-sm btn-action btn-action-danger']) }}
    aria-label="{{ $label }}"
    title="Hapus"
    data-bs-toggle="modal"
    data-bs-target="#confirmDeleteModal"
    data-delete-url="{{ $url }}"
    data-delete-message="{{ $deleteMessage }}"
    @if ($deleteBlockedMessage)
        data-delete-blocked-message="{{ $deleteBlockedMessage }}"
    @endif>
    <i class="bi bi-trash" aria-hidden="true"></i>
</button>
