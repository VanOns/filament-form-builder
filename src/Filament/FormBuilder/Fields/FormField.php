<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeHidden;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeRequired;
use VanOns\FilamentFormBuilder\Traits\Fields\HasAttributes;
use VanOns\FilamentFormBuilder\Traits\Fields\HasConditions;
use VanOns\FilamentFormBuilder\Traits\Fields\HasDefaultValue;
use VanOns\FilamentFormBuilder\Traits\Fields\HasFields;
use VanOns\FilamentFormBuilder\Traits\Fields\HasKey;
use VanOns\FilamentFormBuilder\Traits\Fields\HasLabel;
use VanOns\FilamentFormBuilder\Traits\Fields\HasRules;
use VanOns\FilamentFormBuilder\Traits\Fields\HasSubmissionColumns;
use VanOns\FilamentFormBuilder\Traits\Fields\HasView;

abstract class FormField
{
    use HasKey;
    use HasLabel;
    use HasRules;
    use HasView;
    use HasFields;
    use CanBeRequired;
    use CanBeHidden;
    use HasDefaultValue;
    use HasConditions;
    use HasAttributes;
    use HasSubmissionColumns;

    /**
     * The wrapper carries where the field sits in the form's grid, so a project
     * can lay the form out from CSS alone without overriding any view.
     */
    public function getWrapperAttributes(): HtmlString
    {
        $span = $this->getColumnSpan($this->getGridColumns());

        return $this->getAttributes([
            'data-form-builder-input-wrapper' => $this->getKey(),
            'data-form-builder-column-span' => (string) $span,
            'style' => "--form-builder-column-span:{$span}",
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public static function isInput(): bool
    {
        return true;
    }

    public static function icon(): string | BackedEnum
    {
        return Heroicon::OutlinedPencil;
    }


    /**
     * @return string
     */
    public function render(): string
    {
        return Blade::render(
            $this->getView(),
            ['field' => $this],
        );
    }
}
