@extends('layouts.app')

@section('title', $productRequest->ticket_number.' - Triase Request Produk')

@section('content')
    @php
        $allowedStatusValues = collect([$productRequest->status, ...$productRequest->status->allowedOwnerTransitions()])
            ->map(fn ($status) => $status->value)
            ->unique();
    @endphp

    <x-app-page width="wide" long-footer>
        <div class="request-detail-heading request-detail-heading--owner">
            <div>
                <a class="request-back-link" href="{{ route('owner.product-requests.index') }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke inbox
                </a>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <span class="request-ticket">{{ $productRequest->ticket_number }}</span>
                    <span class="product-request-status product-request-status--{{ $productRequest->status->tone() }}">{{ $productRequest->status->label() }}</span>
                    <span class="owner-context-chip"><i class="bi bi-building" aria-hidden="true"></i>{{ $productRequest->koperasi->nama }}</span>
                </div>
                <h1>{{ $productRequest->title }}</h1>
                <p>{{ $productRequest->type->label() }} · {{ $productRequest->creator?->name ?? 'Pengguna tidak aktif' }} · {{ $productRequest->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</p>
            </div>
        </div>

        <x-flash-alert />

        <div class="owner-request-workspace">
            <main class="request-conversation" aria-labelledby="ownerConversationTitle">
                <h2 id="ownerConversationTitle">Percakapan dan catatan</h2>

                <ol class="request-timeline">
                    <li class="request-message request-message--origin">
                        <div class="request-message__avatar" aria-hidden="true">{{ $productRequest->creator?->initials() ?? '?' }}</div>
                        <article>
                            <header>
                                <div><strong>{{ $productRequest->creator?->name ?? 'Pengguna tidak aktif' }}</strong><span>mengajukan request</span></div>
                                <time datetime="{{ $productRequest->created_at->toIso8601String() }}">{{ $productRequest->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</time>
                            </header>
                            <div class="request-message__body">{!! nl2br(e($productRequest->description)) !!}</div>
                            @if($productRequest->initialAttachments->isNotEmpty())
                                <div class="request-attachments">
                                    @foreach($productRequest->initialAttachments as $attachment)
                                        <a href="{{ route('owner.product-requests.attachments.download', [$productRequest->ticket_number, $attachment->id]) }}">
                                            <i class="bi bi-paperclip" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span>
                                            <small>{{ number_format($attachment->size_bytes / 1024, 0, ',', '.') }} KB</small>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    </li>

                    @foreach($productRequest->messages as $entry)
                        @php($isInternal = $entry->visibility === \App\Enums\ProductRequestMessageVisibility::Internal)
                        <li class="request-message {{ $isInternal ? 'request-message--internal' : ($entry->author?->isSystemOwner() ? 'request-message--owner' : '') }}">
                            <div class="request-message__avatar" aria-hidden="true">
                                @if($isInternal)<i class="bi bi-lock-fill"></i>@else{{ $entry->author?->initials() ?? '?' }}@endif
                            </div>
                            <article>
                                <header>
                                    <div>
                                        <strong>{{ $entry->author?->name ?? 'Pengguna tidak aktif' }}</strong>
                                        @if($isInternal)
                                            <span class="request-internal-label"><i class="bi bi-eye-slash" aria-hidden="true"></i> Hanya system owner</span>
                                        @elseif($entry->author?->isSystemOwner())
                                            <span class="request-author-label">Balasan tim produk</span>
                                        @endif
                                    </div>
                                    <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</time>
                                </header>
                                <div class="request-message__body">{!! nl2br(e($entry->body)) !!}</div>
                                @if($entry->attachments->isNotEmpty())
                                    <div class="request-attachments">
                                        @foreach($entry->attachments as $attachment)
                                            <a href="{{ route('owner.product-requests.attachments.download', [$productRequest->ticket_number, $attachment->id]) }}">
                                                <i class="bi bi-paperclip" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span>
                                                <small>{{ number_format($attachment->size_bytes / 1024, 0, ',', '.') }} KB</small>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ol>

                <div class="owner-composer-grid">
                    <section class="request-reply-card" aria-labelledby="publicReplyTitle">
                        <span class="owner-composer-kicker"><i class="bi bi-chat-square-dots" aria-hidden="true"></i> Terlihat tenant</span>
                        <h2 id="publicReplyTitle">Balasan publik</h2>
                        <p>Balasan ini masuk ke timeline tenant dan memicu notifikasi kepada peserta request.</p>
                        <form method="POST" action="{{ route('owner.product-requests.messages.store', $productRequest->ticket_number) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="visibility" value="public">
                            <label class="form-label" for="public_body">Balasan</label>
                            <textarea class="form-control" id="public_body" name="body" rows="5" maxlength="10000" required>{{ old('visibility') === 'public' ? old('body') : '' }}</textarea>
                            <div class="mt-3">
                                <x-form.file name="attachments" label="Lampiran publik" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.txt" help="Maksimal 3 file dan 5 MB per file." />
                            </div>
                            @if(old('visibility') === 'public' && $errors->any())
                                <div class="text-danger small mt-2">{{ $errors->first() }}</div>
                            @endif
                            <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Kirim ke tenant</button>
                        </form>
                    </section>

                    <section class="request-reply-card request-reply-card--internal" aria-labelledby="internalNoteTitle">
                        <span class="owner-composer-kicker"><i class="bi bi-lock" aria-hidden="true"></i> Privat</span>
                        <h2 id="internalNoteTitle">Catatan internal</h2>
                        <p>Tidak tampil dalam jumlah pesan, aktivitas, lampiran, maupun notifikasi tenant.</p>
                        <form method="POST" action="{{ route('owner.product-requests.messages.store', $productRequest->ticket_number) }}">
                            @csrf
                            <input type="hidden" name="visibility" value="internal">
                            <label class="form-label" for="internal_body">Catatan</label>
                            <textarea class="form-control" id="internal_body" name="body" rows="5" maxlength="10000" required>{{ old('visibility') === 'internal' ? old('body') : '' }}</textarea>
                            @if(old('visibility') === 'internal' && $errors->any())
                                <div class="text-danger small mt-2">{{ $errors->first() }}</div>
                            @endif
                            <button class="btn btn-dark mt-3" type="submit"><i class="bi bi-lock-fill" aria-hidden="true"></i> Simpan internal</button>
                        </form>
                    </section>
                </div>
            </main>

            <aside class="owner-triage-panel">
                <x-section-card title="Triase" subtitle="Perubahan status dapat dilihat tenant.">
                    <form method="POST" action="{{ route('owner.product-requests.triage.update', $productRequest->ticket_number) }}">
                        @csrf
                        @method('PATCH')
                        <div class="vstack gap-3">
                            <div>
                                <label class="form-label" for="triage_status">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="triage_status" name="status" required>
                                    @foreach($statuses as $value => $label)
                                        @if($allowedStatusValues->contains($value))
                                            <option value="{{ $value }}" @selected(old('status', $productRequest->status->value) === $value)>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label class="form-label" for="internal_priority">Prioritas internal</label>
                                <select class="form-select" id="internal_priority" name="internal_priority">
                                    <option value="">Belum dinilai</option>
                                    @foreach($priorities as $value => $label)
                                        <option value="{{ $value }}" @selected(old('internal_priority', $productRequest->internal_priority?->value) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="assigned_to">Penanggung jawab</label>
                                <select class="form-select @error('assigned_to') is-invalid @enderror" id="assigned_to" name="assigned_to">
                                    <option value="">Belum ditugaskan</option>
                                    @foreach($owners as $owner)
                                        <option value="{{ $owner->id }}" @selected((string) old('assigned_to', $productRequest->assigned_to) === (string) $owner->id)>{{ $owner->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label class="form-label" for="duplicate_ticket">Tiket sumber duplikat</label>
                                <input class="form-control @error('duplicate_ticket') is-invalid @enderror" id="duplicate_ticket" name="duplicate_ticket"
                                    value="{{ old('duplicate_ticket', $productRequest->duplicateOf?->ticket_number) }}" placeholder="REQ-2026-000001">
                                @error('duplicate_ticket')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Wajib saat status Duplikat dan harus dari koperasi yang sama.</div>
                            </div>
                            <div>
                                <label class="form-label" for="triage_reason">Alasan perubahan status</label>
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="triage_reason" name="reason" rows="3" maxlength="500">{{ old('reason') }}</textarea>
                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Alasan ini terlihat oleh tenant. Gunakan catatan internal untuk pembahasan privat.</div>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Simpan triase</button>
                        </div>
                    </form>
                </x-section-card>

                <x-section-card title="Konteks request" class="mt-3">
                    <dl class="request-facts">
                        <div><dt>Koperasi</dt><dd>{{ $productRequest->koperasi->nama }}</dd></div>
                        <div><dt>Pengaju</dt><dd>{{ $productRequest->creator?->name ?? 'Pengguna tidak aktif' }}</dd></div>
                        <div><dt>Dampak pengaju</dt><dd>{{ $productRequest->requester_priority->label() }}</dd></div>
                        <div><dt>Area</dt><dd>{{ $productRequest->module ? config('product_requests.modules.'.$productRequest->module, $productRequest->module) : 'Lintas area' }}</dd></div>
                        <div><dt>Respons pertama</dt><dd>{{ $productRequest->first_responded_at?->locale('id')->diffForHumans() ?? 'Belum ada' }}</dd></div>
                    </dl>
                </x-section-card>

                <x-section-card title="Riwayat status" class="mt-3">
                    <ol class="request-status-history">
                        @foreach($productRequest->statusHistories as $history)
                            <li>
                                <span aria-hidden="true"></span>
                                <div>
                                    <strong>{{ $history->to_status->label() }}</strong>
                                    <time>{{ $history->created_at->locale('id')->translatedFormat('d M Y, H:i') }}</time>
                                    <small>{{ $history->actor?->name ?? 'Akun tidak aktif' }}</small>
                                    @if($history->reason)<p>{{ $history->reason }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-section-card>
            </aside>
        </div>
    </x-app-page>
@endsection
