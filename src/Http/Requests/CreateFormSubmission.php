<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use VanOns\FilamentFormBuilder\Models\Form;

class CreateFormSubmission extends FormRequest
{
    public Form $form;

    public function getForm(): Form
    {
        return $this->form ??= Form::query()->findOrFail($this->route('formId'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->getForm()->getRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->getForm()->getSubmissionFields();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->getForm()->getType()->messages();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->getForm()->getType()->beforeValidation($this->all());
    }
}
