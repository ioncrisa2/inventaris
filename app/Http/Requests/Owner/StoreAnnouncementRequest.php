<?php

namespace App\Http\Requests\Owner;

use App\Models\PlatformAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSystemOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(PlatformAnnouncement::PRIORITIES)],
            'target_koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('starts_at')) && filled($this->input('ends_at'))) {
            $this->merge(['starts_at' => now()->toDateTimeString()]);
        }
    }
}
