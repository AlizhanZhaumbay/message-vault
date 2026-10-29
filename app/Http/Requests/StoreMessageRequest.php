<?php

namespace App\Http\Requests;

use App\Enums\DestroyMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'teaser' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:20000'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],

            'unlock_at' => ['nullable', 'date', 'after:now'],
            'destroy_mode' => ['required', Rule::enum(DestroyMode::class)],
            'max_views' => ['exclude_unless:destroy_mode,after_views', 'required', 'integer', 'min:1', 'max:1000'],
            'destroy_at' => [
                'exclude_unless:destroy_mode,at_date', 'required', 'date', 'after:now',
                Rule::when($this->filled('unlock_at'), 'after:unlock_at'),
            ],

            'attachments' => ['nullable', 'array', 'max:'.config('vault.max_files')],
            'attachments.*' => [
                'file',
                'max:'.config('vault.max_file_size_kb'),
                'mimetypes:'.implode(',', config('vault.allowed_mime_types')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'A message needs a body, attachments, or both.',
            'max_views.required' => 'max_views is required when destroy_mode is after_views.',
            'destroy_at.required' => 'destroy_at is required when destroy_mode is at_date.',
            'destroy_at.after' => 'destroy_at must be in the future and later than unlock_at.',
        ];
    }
}
