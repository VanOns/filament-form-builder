<?php

namespace VanOns\FilamentFormBuilder\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

class ContactForm extends FormComponent
{
    public function __construct(public Form $form)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'submitter_email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:255',
            'message' => 'required|string|max:6000',
        ];
    }

    public static function placeholders(): array
    {
        return [
            'name',
            'company_name',
            'submitter_email',
            'phone_number',
            'message',
        ];
    }

    public function render(): View|Closure|string
    {
        return view('filament-form-builder::components.forms.contact-form');
    }
}
