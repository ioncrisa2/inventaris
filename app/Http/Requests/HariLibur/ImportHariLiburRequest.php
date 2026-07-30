<?php

namespace App\Http\Requests\HariLibur;

use App\Models\HariLibur;
use Illuminate\Foundation\Http\FormRequest;

class ImportHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', HariLibur::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ];
    }
}
