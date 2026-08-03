@props([
    'koperasis',
    'selected' => null,
    'id' => 'koperasi_id',
])

@if(auth()->user()->isSuperAdmin())
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="visually-hidden" for="{{ $id }}">Koperasi</label>
        <select class="form-select" id="{{ $id }}" name="koperasi_id">
            <option value="">Seluruh koperasi</option>
            @foreach($koperasis as $koperasi)
                <option value="{{ $koperasi->id }}" @selected((string) $selected === (string) $koperasi->id)>
                    {{ $koperasi->nama }}
                </option>
            @endforeach
        </select>
    </div>
@endif
