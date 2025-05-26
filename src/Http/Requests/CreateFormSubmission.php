<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'callback_url' => ['sometimes', 'string'],
        ];

        return array_merge($rules, $this->getFormRules());
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormRules(): array
    {
        $form = $this->getForm();

        $rules = [];

        if (method_exists($form->template, 'rules')) {
            $rules = $form->template::rules();
        } elseif ($form->template === 'custom') {
            $rules = $form->getCustomFormRules();
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->getForm()->getCustomFormLabels();
    }
}
