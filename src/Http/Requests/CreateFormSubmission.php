<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFormSubmission extends FormRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submitter_email' => ['sometimes', 'nullable', 'string'],
            'callback_url' => ['sometimes', 'string'],
        ];
    }
}
