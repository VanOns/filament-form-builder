<?php

namespace VanOns\FilamentFormBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

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
        $template = $form->template;

        $rules = [];

        try {
            if ($template !== 'custom' && (new ReflectionClass($template))->isSubclassOf(FormComponent::class)) {
                /**
                 * @var class-string<FormComponent> $template
                 */
                $rules = $template::getRules();
            } elseif ($form->isCustom()) {
                $rules = $form->getCustomFormRules();
            }

            return $rules;
        } catch (\Exception) {
            return [];
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
}
