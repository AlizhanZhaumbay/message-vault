<?php

namespace App\Http\Requests;

use App\Enums\MessageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(MessageStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function status(): ?MessageStatus
    {
        return $this->enum('status', MessageStatus::class);
    }
}
