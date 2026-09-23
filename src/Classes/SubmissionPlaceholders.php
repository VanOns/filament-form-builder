<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Exception;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

class SubmissionPlaceholders
{
    /**
     * @var array<string, string>|null
     */
    protected ?array $values = null;

    public function __construct(public FormSubmission $formSubmission)
    {
    }

    public static function make(FormSubmission $formSubmission): SubmissionPlaceholders
    {
        return new SubmissionPlaceholders($formSubmission);
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        // Later keys win: a submitted field beats a template fallback, and
        // `form_title` when an editor named a field that way.
        $values = array_filter([
            'form_title' => $this->formSubmission->form->title,
            ...$this->templatePlaceholders(),
            ...$this->formSubmission->getFormattedData(
                data: $this->formSubmission->modifyDataValuesUsing($this->formSubmission->data),
            ),
            'submitter_email' => $this->formSubmission->submitter_email,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->values = $this->withoutKeyPrefixAliases($values);
    }

    public function replace(string $subject, ?callable $escape = null): string
    {
        $replacements = [];

        foreach ($this->values() as $key => $value) {
            $value = (string) $value;

            if ($escape !== null) {
                $value = $escape($value);
            }

            $replacements['{{ $' . $key . ' }}'] = $value;
            $replacements['{{$' . $key . '}}'] = $value;
        }

        return $replacements === [] ? $subject : strtr($subject, $replacements);
    }

    public function appendQuery(?string $url, ?string $query): ?string
    {
        $query = trim((string) $query);

        if ($url === null || $url === '' || $query === '') {
            return $url;
        }

        $query = $this->replace($query, rawurlencode(...));

        // Unfilled tags leave their parameter empty rather than the raw placeholder.
        $query = trim(preg_replace('/{{\s*\$[^}]*}}/', '', $query) ?? $query, "?& \t\n\r\0\x0B");

        if ($query === '') {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        $base .= (str_contains($base, '?') ? '&' : '?') . $query;

        return $fragment === null ? $base : $base . '#' . $fragment;
    }

    /**
     * Fallbacks the template declares, so an empty field renders as "-".
     *
     * @return array<string, string>
     */
    protected function templatePlaceholders(): array
    {
        try {
            if (TemplateHelper::isTemplate($this->formSubmission->form->template)) {
                return $this->formSubmission->form->getFormComponent()->getPlaceholders();
            }
        } catch (Exception) {
            return [];
        }

        return [];
    }

    /**
     * Forms from before the `key_` prefix used `{{ $naam }}`, so register both.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withoutKeyPrefixAliases(array $values): array
    {
        $prefix = 'key_';
        $aliases = [];

        foreach ($values as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $alias = Str::after($key, $prefix);

                if (!array_key_exists($alias, $values)) {
                    $aliases[$alias] = $value;
                }
            }
        }

        return array_merge($values, $aliases);
    }
}
