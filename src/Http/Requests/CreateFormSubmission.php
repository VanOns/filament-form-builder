<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use VanOns\FilamentFormBuilder\Classes\Honeypot;
use VanOns\FilamentFormBuilder\Models\Form;

class CreateFormSubmission extends FormRequest
{
    public Form $form;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $prepared = null;

    protected bool $caught = false;

    public function getForm(): Form
    {
        return $this->form ??= Form::query()->findOrFail($this->route('formId'));
    }

    // A bot gets no validation errors to learn from; the controller answers it as if it got through.
    public function validateResolved(): void
    {
        $this->caught = Honeypot::caught($this, $this->getForm());

        if (! $this->caught) {
            parent::validateResolved();
        }
    }

    public function isCaught(): bool
    {
        return $this->caught;
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
     * The input as the form type prepared it: what is validated is also what
     * is stored.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->prepared ??= $this->getForm()->getType()->beforeValidation($this->all());
    }
}
