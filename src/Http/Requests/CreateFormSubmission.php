<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Models\Form;

class CreateFormSubmission extends FormRequest
{
    public Form $form;

    protected function getForm(): Form
    {
        if (!isset($this->form)) {
            $this->form = Form::query()
                ->findOrFail($this->route('formId'));
        }

        return $this->form;
    }

    /**
     * @return array<string, mixed>
    */
    public function rules(): array
    {
        $rules = [
            'submitter_email' => ['sometimes', 'nullable', 'string', 'email:rfc'],
        ];

        return array_merge($rules, $this->getFormRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormRules(): array
    {
        $form = $this->getForm();
        $template = $form->template;

        $rules = [];

        try {
            if (is_subclass_of($template, FilamentForm::class)) {
                /**
                 * @var class-string<FilamentForm> $template
                 */
                $rules = $form->getFormComponent()->getRules();
            }

            return $rules;
        } catch (\Exception) {
            return $rules;
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->getForm()->getFormAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->getForm()->getFormMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        if ($template = $this->getForm()->template) {
            return $template::modifyDataBeforeValidation($this->all());
        }

        return $this->all();
    }
}
