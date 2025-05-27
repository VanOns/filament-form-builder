<?php

namespace VanOns\FilamentFormBuilder\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use VanOns\FilamentFormBuilder\View\Components\CustomFormRenderer;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class CustomForm extends FormComponent
{
    public static function isCustom(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->form->getCustomFormRules();
    }

    public function placeholders(): array
    {
        return $this->form->getCustomFormKeys();
    }

    public function attributes(): array
    {
        return $this->form->getCustomFormLabels();
    }

    public function render(): View|Closure|string
    {
        return Blade::renderComponent(new CustomFormRenderer($this->form));
    }
}
