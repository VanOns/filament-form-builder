<?php

namespace VanOns\FilamentFormBuilder\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Picks a form, for a page block or anything else that shows one.
 */
class FormSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(trans_choice('filament-form-builder::general.models.form.label', 1));
        $this->searchable();
        $this->options(fn (): array => Form::query()->orderBy('title')->pluck('title', 'id')->all());
    }
}
