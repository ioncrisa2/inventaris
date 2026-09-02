@extends('layouts.app')

@section('title', $productRequest->ticket_number.' - Request Produk')

@section('content')
    <x-app-page width="wide">
        <div class="request-detail-heading">
            <div>
                <a class="request-back-link" href="{{ route('product-requests.index') }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar
                </a>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <span class="request-ticket">{{ $productRequest->ticket_number }}</span>
                    <span class="product-request-status product-request-status--{{ $productRequest->status->tone() }}">
                        {{ $productRequest->status->label() }}
                    </span>
                </div>
                <h1>{{ $productRequest->title }}</h1>
                <p>
                    {{ $productRequest->type->label() }}
                    @if($productRequest->module)
                        · {{ config('product_requests.modules.'.$productRequest->module, $productRequest->module) }}
                    @endif
                    · Diajukan {{ $productRequest->created_at->locale('id')->diffForHumans() }}
                </p>
            </div>
            @if($canClose)
                <form method="POST" action="{{ route('product-requests.state.toggle', $productRequest->ticket_number) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn {{ $productRequest->status === \App\Enums\ProductRequestStatus::Closed ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">
                        <i class="bi {{ $productRequest->status === \App\Enums\ProductRequestStatus::Closed ? 'bi-arrow-counterclockwise' : 'bi-archive' }}" aria-hidden="true"></i>
                        {{ $productRequest->status === \App\Enums\ProductRequestStatus::Closed ? 'Buka kembali' : 'Tutup request' }}
                    </button>
                </form>
            @endif
        </div>

        <x-flash-alert />

        @if($productRequest->status === \App\Enums\ProductRequestStatus::Duplicate && $productRequest->duplicateOf)
            <div class="alert alert-secondary request-duplicate-note" role="status">
                <i class="bi bi-intersect" aria-hidden="true"></i>
                Request ini ditandai duplikat dari
                <a href="{{ route('product-requests.show', $productRequest->duplicateOf->ticket_number) }}">
                    {{ $productRequest->duplicateOf->ticket_number }} — {{ $productRequest->duplicateOf->title }}
                </a>.
            </div>
        @endif

        <div class="request-detail-grid">
            <main class="request-conversation" aria-labelledby="conversationTitle">
                <h2 id="conversationTitle">Percakapan</h2>

                <ol class="request-timeline">
                    <li class="request-message request-message--origin">
                        <div class="request-message__avatar" aria-hidden="true">{{ $productRequest->creator?->initials() ?? '?' }}</div>
                        <article>
                            <header>
                                <div>
                                    <strong>{{ $productRequest->creator?->name ?? 'Pengguna tidak aktif' }}</strong>
                                    <span>mengajukan request</span>
                                </div>
                                <time datetime="{{ $productRequest->created_at->toIso8601String() }}">
                                    {{ $productRequest->created_at->locale('id')->translatedFormat('d M Y, H:i') }}
                                </time>
                            </header>
                            <div class="request-message__body">{!! nl2br(e($productRequest->description)) !!}</div>
                            @if($productRequest->initialAttachments->isNotEmpty())
                                <div class="request-attachments" aria-label="Lampiran pengajuan">
                                    @foreach($productRequest->initialAttachments as $attachment)
                                        @php($storedFile = $attachment->storedFiles->sortByDesc('id')->first())
                                        @if($storedFile && !$storedFile->isAvailable())
                                        <span class="d-inline-flex align-items-center gap-2 px-2 py-1 border rounded text-body-secondary small" role="status">
                                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                            <span>{{ $attachment->original_name }}</span>
                                            <small>{{ $storedFile->status === 'infected' ? 'Ditolak' : ($storedFile->status === 'failed' ? 'Gagal diproses' : 'Sedang diproses') }}</small>
                                        </span>
                                        @else
                                        <a href="{{ route('product-requests.attachments.download', [$productRequest->ticket_number, $attachment->id]) }}">
                                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                                            <span>{{ $attachment->original_name }}</span>
                                            <small>{{ number_format($attachment->size_bytes / 1024, 0, ',', '.') }} KB</small>
                                        </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    </li>

                    @foreach($productRequest->publicMessages as $message)
                        <li class="request-message {{ $message->author?->isSystemOwner() ? 'request-message--owner' : '' }}">
                            <div class="request-message__avatar" aria-hidden="true">{{ $message->author?->initials() ?? '?' }}</div>
                            <article>
                                <header>
                                    <div>
                                        <strong>{{ $message->author?->name ?? 'Pengguna tidak aktif' }}</strong>
                                        @if($message->author?->isSystemOwner())
                                            <span class="request-author-label">Tim produk</span>
                                        @endif
                                    </div>
                                    <time datetime="{{ $message->created_at->toIso8601String() }}">
                                        {{ $message->created_at->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </time>
                                </header>
                                <div class="request-message__body">{!! nl2br(e($message->body)) !!}</div>
                                @if($message->attachments->isNotEmpty())
                                    <div class="request-attachments" aria-label="Lampiran balasan">
                                        @foreach($message->attachments as $attachment)
                                            @php($storedFile = $attachment->storedFiles->sortByDesc('id')->first())
                                            @if($storedFile && !$storedFile->isAvailable())
                                            <span class="d-inline-flex align-items-center gap-2 px-2 py-1 border rounded text-body-secondary small" role="status">
                                                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                                <span>{{ $attachment->original_name }}</span>
                                                <small>{{ $storedFile->status === 'infected' ? 'Ditolak' : ($storedFile->status === 'failed' ? 'Gagal diproses' : 'Sedang diproses') }}</small>
                                            </span>
                                            @else
                                            <a href="{{ route('product-requests.attachments.download', [$productRequest->ticket_number, $attachment->id]) }}">
                                                <i class="bi bi-paperclip" aria-hidden="true"></i>
                                                <span>{{ $attachment->original_name }}</span>
                                                <small>{{ number_format($attachment->size_bytes / 1024, 0, ',', '.') }} KB</small>
                                            </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ol>

                @if($canReply)
                    <section class="request-reply-card" aria-labelledby="replyTitle">
                        <h2 id="replyTitle">Kirim balasan</h2>
                        <p>Tambahkan informasi yang membantu tim produk menindaklanjuti request.</p>
                        <form method="POST" action="{{ route('product-requests.messages.store', $productRequest->ticket_number) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label" for="body">Balasan</label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="5"
                                maxlength="10000" required>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="mt-3">
                                <x-form.file name="attachments" label="Lampiran" policy="product_attachments" multiple />
                                @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-send" aria-hidden="true"></i> Kirim balasan
                                </button>
                            </div>
                        </form>
                    </section>
                @else
                    <div class="request-locked-note">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <div>
                            <strong>Percakapan tidak menerima balasan baru</strong>
                            <p>Status saat ini adalah {{ $productRequest->status->label() }}.</p>
                        </div>
                    </div>
                @endif
            </main>

            <aside class="request-detail-sidebar">
                <x-section-card title="Ringkasan">
                    <dl class="request-facts">
                        <div><dt>Status</dt><dd>{{ $productRequest->status->label() }}</dd></div>
                        <div><dt>Jenis</dt><dd>{{ $productRequest->type->label() }}</dd></div>
                        <div><dt>Dampak</dt><dd>{{ $productRequest->requester_priority->label() }}</dd></div>
                        <div><dt>Area</dt><dd>{{ $productRequest->module ? config('product_requests.modules.'.$productRequest->module, $productRequest->module) : 'Lintas area' }}</dd></div>
                        <div><dt>Aktivitas publik</dt><dd>{{ $productRequest->last_activity_at->locale('id')->diffForHumans() }}</dd></div>
                    </dl>
                </x-section-card>

                <x-section-card title="Perjalanan status" class="mt-3">
                    <ol class="request-status-history">
                        @foreach($productRequest->statusHistories as $history)
                            <li>
                                <span aria-hidden="true"></span>
                                <div>
                                    <strong>{{ $history->to_status->label() }}</strong>
                                    <time datetime="{{ $history->created_at->toIso8601String() }}">
                                        {{ $history->created_at->locale('id')->translatedFormat('d M Y, H:i') }}
                                    </time>
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
