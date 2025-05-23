<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use VanOns\FilamentFormBuilder\Models\Form;

class CreateFormSubmission extends FormRequest
{
    /**
     * @return array<string, mixed>
    */
    public function rules(): array
    {
        $rules = [
            'submitter_email' => ['sometimes', 'nullable', 'string', 'email:rfc'],
            'callback_url' => ['sometimes', 'string'],
        ];

        return array_merge($rules, $this->getFormRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormRules(): array
    {
        $form = Form::query()
            ->findOrFail(Route::current()->parameter('formId'));

        $rules = [];

        if (method_exists($form->template, 'rules')) {
            $rules = $form->template::rules();
        } elseif ($form->template === 'custom') {
            $rules = $form->getCustomFormRules();
        }

        return $rules;
    }
}
