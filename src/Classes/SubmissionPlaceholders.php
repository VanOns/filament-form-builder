<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Exception;
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

        // Later keys win: a submitted field beats a template fallback.
        return $this->values = array_filter([
            'form_title' => $this->formSubmission->form->title,
            ...$this->templatePlaceholders(),
            ...$this->formSubmission->getFormattedData(),
            'submitter_email' => $this->formSubmission->submitter_email,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Points every `{{ $from }}` in the given text, or in the strings of the
     * given array, at `$to` instead.
     */
    public static function rename(mixed $value, string $from, string $to): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => static::rename($item, $from, $to), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/{{\s*\$' . preg_quote($from, '/') . '\s*}}/', '{{ $' . $to . ' }}', $value) ?? $value;
    }

    /**
     * One pass over the subject, so a filled-in value is never searched for
     * placeholders of its own.
     */
    public function replace(string $subject, ?callable $escape = null, bool $removeUnknown = false): string
    {
        $values = $this->values();

        return preg_replace_callback('/{{\s*\$([^\s{}]+)\s*}}/u', function (array $match) use ($values, $escape, $removeUnknown): string {
            if (!array_key_exists($match[1], $values)) {
                return $removeUnknown ? '' : $match[0];
            }

            $value = (string) $values[$match[1]];

            return $escape !== null ? $escape($value) : $value;
        }, $subject) ?? $subject;
    }

    public function appendQuery(?string $url, ?string $query): ?string
    {
        $query = trim((string) $query);

        if ($url === null || $url === '' || $query === '') {
            return $url;
        }

        // Unfilled tags leave their parameter empty rather than the raw placeholder.
        $query = trim($this->replace($query, rawurlencode(...), removeUnknown: true), "?& \t\n\r\0\x0B");

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

}
