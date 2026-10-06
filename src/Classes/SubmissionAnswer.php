<?php

namespace VanOns\FilamentFormBuilder\Classes;

use BackedEnum;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;

/**
 * One answer as the detail page shows it: a field with everything it holds,
 * or a key the form no longer has a field for.
 */
final class SubmissionAnswer
{
    /**
     * @param  array<string, string>  $columns  key => label of each value a field with several holds
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly mixed $value,
        public readonly mixed $raw,
        public readonly string | BackedEnum $icon,
        public readonly string $view,
        public readonly ?FormField $field = null,
        public readonly ?string $badge = null,
        public readonly ?string $note = null,
        public readonly array $columns = [],
    ) {
    }
}
