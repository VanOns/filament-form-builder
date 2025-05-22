<?php

namespace VanOns\FilamentFormBuilder\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form as FormModel;

class CustomFormRenderer extends Component
{
    public function __construct(public ?FormModel $form)
    {
    }

    public function render(): View|Closure|string
    {
        $fields = $this->form->custom['fields'] ?? [];
        return view('filament-form-builder::components.custom-form-renderer', compact('fields'));
    }
}
