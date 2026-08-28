<?php

namespace App\Http\Requests\Karyawan;

use App\Models\Karyawan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $karyawan = $this->route('karyawan');

        return $karyawan instanceof Karyawan
            && ($this->user()?->can('manageAccount', $karyawan) ?? false);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('koperasi_id', $this->user()?->koperasi_id ?? -1)
                ),
            ],
        ];
    }
}
