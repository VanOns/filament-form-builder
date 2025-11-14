<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields;

use Filament\Forms\Components\Component;
use Illuminate\Support\Facades\Blade;
use VanOns\FilamentFormBuilder\Traits\Fields\CanBeRequired;
use VanOns\FilamentFormBuilder\Traits\Fields\HasAttributes;
use VanOns\FilamentFormBuilder\Traits\Fields\HasFields;
use VanOns\FilamentFormBuilder\Traits\Fields\HasHelperText;
use VanOns\FilamentFormBuilder\Traits\Fields\HasKey;
use VanOns\FilamentFormBuilder\Traits\Fields\HasLabel;
use VanOns\FilamentFormBuilder\Traits\Fields\HasRules;
use VanOns\FilamentFormBuilder\Traits\Fields\HasView;
use VanOns\FilamentFormBuilder\Traits\Fields\HasVisibility;

abstract class FormField
{
    use HasKey;
    use HasLabel;
    use HasRules;
    use HasHelperText;
    use HasView;
    use HasFields;
    use CanBeRequired;
    use HasVisibility;
    use HasAttributes;

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

    /**
     * @return array<Component>
     */
    public function make(): array
    {
        return static::getFields();
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
