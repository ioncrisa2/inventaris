<?php

namespace App\Http\Requests\DokumenKaryawan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('karyawan'));
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => ['required', Rule::in(config('kepegawaian.jenis_dokumen'))],
            'dokumen' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
