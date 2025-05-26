<?php

namespace VanOns\FilamentFormBuilder\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use VanOns\FilamentFormBuilder\Models\Form;

class ContactForm extends Component
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

    public function render(): View|Closure|string
    {
        return view('filament-form-builder::components.forms.contact-form');
    }
}
