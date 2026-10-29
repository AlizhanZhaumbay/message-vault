<?php

namespace App\Http\Requests;

use App\Enums\DestroyMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'teaser' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:100'],

            'unlock_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'destroy_mode' => ['sometimes', 'required', Rule::enum(DestroyMode::class)],
            'max_views' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'destroy_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $message = $this->route('message');

            $mode = DestroyMode::from($this->input('destroy_mode', $message->destroy_mode->value));
            $maxViews = $this->exists('max_views') ? $this->input('max_views') : $message->max_views;
            $destroyAt = $this->exists('destroy_at') ? $this->date('destroy_at') : $message->destroy_at;
            $unlockAt = $this->exists('unlock_at') ? $this->date('unlock_at') : $message->unlock_at;

            if ($mode === DestroyMode::AfterViews && $maxViews === null) {
                $validator->errors()->add('max_views', 'max_views is required when destroy_mode is after_views.');
            }

            if ($mode === DestroyMode::AtDate && $destroyAt === null) {
                $validator->errors()->add('destroy_at', 'destroy_at is required when destroy_mode is at_date.');
            }

            if ($mode === DestroyMode::AtDate && $destroyAt instanceof Carbon && $unlockAt instanceof Carbon && $destroyAt->lte($unlockAt)) {
                $validator->errors()->add('destroy_at', 'destroy_at must be later than unlock_at.');
            }
        }];
    }
}
