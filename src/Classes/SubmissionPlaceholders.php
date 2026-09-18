<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Exception;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * The placeholder values available for one submission, shared by everything that
 * writes them out: the e-mail notification and the confirmation query string.
 *
 * Keys are placeholder names without the `$`, so `key_naam` is written as
 * `{{ $key_naam }}` by an editor.
 */
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
     * The submitted values plus the form-wide placeholders, with the template's
     * fallbacks for fields that were left empty.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        // Order matters: a submitted field wins from a template fallback, and
        // from `form_title` when an editor named a field that way.
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

    /**
     * Replace every `{{ $placeholder }}` in a string. Pass an $escape callback to
     * encode the values for their destination (URL, HTML, ...). Placeholders
     * without a value are left untouched; the caller decides what to do with them.
     *
     * strtr walks the subject once and never looks at what it just inserted, so
     * a placeholder that a visitor typed into a field stays literal text.
     *
     * @param  callable(string): string|null  $escape
     */
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

    /**
     * Append a configured query string to a URL, with the placeholders filled in
     * from this submission: `naam={{ $key_naam }}` becomes `?naam=Jesse`.
     *
     * Values are URL-encoded, a placeholder without a value leaves its parameter
     * empty, and a query string or fragment the URL already carries stays intact.
     */
    public function appendQuery(?string $url, ?string $query): ?string
    {
        $query = trim((string) $query);

        if ($url === null || $url === '' || $query === '') {
            return $url;
        }

        $query = $this->replace($query, rawurlencode(...));

        // Drop the tags that had no value, so the parameter arrives empty
        // instead of carrying the placeholder the editor typed.
        $query = trim(preg_replace('/{{\s*\$[^}]*}}/', '', $query) ?? $query, "?& \t\n\r\0\x0B");

        if ($query === '') {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        $base .= (str_contains($base, '?') ? '&' : '?') . $query;

        return $fragment === null ? $base : $base . '#' . $fragment;
    }

    /**
     * Fallback values the form template declares for its fields, so an empty
     * field renders as "-" instead of nothing.
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
     * Forms built before the key prefix existed used `{{ $naam }}` where the data
     * now holds `key_naam`. Register both spellings.
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
