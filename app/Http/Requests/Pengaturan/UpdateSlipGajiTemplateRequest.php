<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSlipGajiTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'pengaturan.slip-gaji.publish' => $this->user()->can('pengaturan.slip-gaji.publish'),
            default => $this->user()->can('pengaturan.slip-gaji.update'),
        };
    }

    public function rules(): array
    {
        return [
            'configuration' => ['required', 'string', 'max:65535'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('configuration')) {
                    return;
                }

                json_decode((string) $this->input('configuration'), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add('configuration', 'Konfigurasi template bukan JSON yang valid.');
                }
            },
        ];
    }

    public function configuration(): array
    {
        return json_decode((string) $this->validated('configuration'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function expectedRevision(): int
    {
        return (int) $this->validated('expected_revision');
    }
}
