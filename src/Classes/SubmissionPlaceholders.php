<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Throwable;
use VanOns\FilamentFormBuilder\Filament\Resources\FormSubmissionResource;
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

        // Later keys win: an answer beats the dash an empty field gets.
        return $this->values = [
            'form_title' => $this->formSubmission->form->title,
            'submission_id' => (string) $this->formSubmission->getKey(),
            'submitted_at' => (string) $this->formSubmission->created_at?->translatedFormat('j F Y, H:i'),
            'submitted_from' => (string) $this->formSubmission->source_url,
            'submission_url' => $this->submissionUrl(),
            ...static::fallbacks($this->formSubmission),
            ...$this->formSubmission->getFormattedData(),
        ];
    }

    /**
     * The submission's page in the panel, or nothing where no panel serves it,
     * such as an app that registers the plugin without the resources.
     */
    public function submissionUrl(): string
    {
        try {
            return FormSubmissionResource::getUrl('view', ['record' => $this->formSubmission]);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Points every `{{ $from }}` in the given text, or in the strings of the
     * given array, at `$to` instead.
     */
    public static function rename(mixed $value, string $from, string $to): mixed
    {
        return MergeTags::rename($value, $from, $to);
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
     * Every answer as a line of its own, label first, for the "all fields" tag.
     */
    public function allFieldsHtml(): string
    {
        $data = [
            ...static::fallbacks($this->formSubmission),
            ...$this->formSubmission->getFormattedData(),
        ];

        return implode('', array_map(
            fn (string | int $key, mixed $value): string => '<p><b>' . e($this->formSubmission->findLabel((string) $key)) . '</b>: ' . e((string) $value) . '</p>',
            array_keys($data),
            $data,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function fallbacks(FormSubmission $submission): array
    {
        return array_fill_keys(array_keys($submission->form?->getSubmissionFields() ?? []), '-');
    }
}
