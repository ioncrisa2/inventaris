@extends('layouts.app')

@section('title', 'Ajukan Request Produk - Sistem Inventaris & Kepegawaian')

@section('content')
    <x-form-page
        class="is-wide"
        title="Ajukan request produk"
        subtitle="Jelaskan kebutuhan dan dampaknya agar tim produk dapat menilai dengan tepat."
        :action="route('product-requests.store')"
        :cancel-route="route('product-requests.index')"
        submit-label="Kirim request"
    >
        <x-slot:top>
            <div class="request-safety-note mb-4" role="note">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <div>
                    <strong>Kirim informasi secukupnya</strong>
                    <p>Jangan menyertakan password, token, atau data pribadi yang tidak diperlukan. Lampiran dapat dibaca oleh tim pengelola produk.</p>
                </div>
            </div>
        </x-slot:top>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                Periksa kembali isian yang ditandai sebelum mengirim request.
            </div>
        @endif

        <div class="row g-4">
            <div class="col-12 col-md-6">
                <label class="form-label" for="type">Jenis request <span class="text-danger">*</span></label>
                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                    <option value="">Pilih jenis</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="module">Area aplikasi</label>
                <select class="form-select @error('module') is-invalid @enderror" id="module" name="module">
                    <option value="">Belum tahu / lintas area</option>
                    @foreach($modules as $value => $label)
                        <option value="{{ $value }}" @selected(old('module') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('module')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <x-form.input name="title" label="Judul singkat" :value="old('title')" required
                    maxlength="180" placeholder="Contoh: Tambahkan ekspor rekap stok per unit" />
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Kebutuhan dan dampak <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                    rows="8" maxlength="10000" required
                    placeholder="Jelaskan kondisi saat ini, hasil yang diharapkan, siapa yang terdampak, dan contoh alurnya.">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Hindari menempel data operasional atau identitas pribadi jika tidak diperlukan.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="requester_priority">Dampak menurut Anda <span class="text-danger">*</span></label>
                <select class="form-select @error('requester_priority') is-invalid @enderror" id="requester_priority"
                    name="requester_priority" required>
                    @foreach($priorities as $value => $label)
                        <option value="{{ $value }}" @selected(old('requester_priority', 'normal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('requester_priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <x-form.file name="attachments" label="Lampiran pendukung" policy="product_attachments" multiple />
                @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </x-form-page>
@endsection
