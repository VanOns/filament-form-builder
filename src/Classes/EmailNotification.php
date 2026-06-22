<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Contracts\FilamentForm;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Mail\MailPanel;

class EmailNotification
{
    public string $subject;
    public string $content;
    public string $sender = '';
    /**
     * @var array<string>
     */
    public ?array $receivers = null;

    /**
     * @param FormSubmission $formSubmission
     * @param array<string, mixed> $notification
     */
    public function __construct(
        public FormSubmission $formSubmission,
        array $notification,
    ) {
        $this->subject = $this->replacePlaceholders($notification['subject'] ?? '');
        $this->content = $this->replacePlaceholders($notification['content'] ?? '');
        $this->content = $this->sanitizeContent($this->content);
        $this->sender = $notification['sender'] ?? '';
        $this->receivers = $this->parseReceivers(
            $notification['receivers'] ?? []
        );
    }

    public function replacePlaceholders(string $content): string
    {
        $formData = $this->getFormSubmissionData(true);

        $data = array_filter([
            'all_fields' => str_contains($content, '$all_fields')
                ? $this->getAllFieldsHtml($formData)
                : null,
            'form_title' => $this->formSubmission->form->title,
            ...$formData,
        ]);

        $data = $this->includePlaceholdersWithoutKeyPrefix($data);

        foreach ($data as $key => $value) {
            $search = '$' . $key;
            $content = str_replace("{{ {$search} }}", $value, $content);
            $content = str_replace("{{{$search}}}", $value, $content);
        }

        return $content;
    }

    /**
     * To support older versions which didn't have the key prefix for custom fields. Add placeholders without the "key_" prefix.
     *
     * @param array<string, mixed> $placeholders
     * @return array<string, mixed>
     */
    protected function includePlaceholdersWithoutKeyPrefix(array $placeholders): array
    {
        $prefix = 'key_';

        $new = [];
        foreach ($placeholders as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $newKey = Str::after($key, $prefix);
                if (!array_key_exists($newKey, $placeholders)) {
                    $new[$newKey] = $value;
                }
            }
        }

        return array_merge($placeholders, $new);
    }

    /**
     * Validate content so no unprocessed placeholders, variables remain. This is to prevent errors.
     *
     * @param string $content
     * @return string
     */
    protected function sanitizeContent(string $content): string
    {
        $content = preg_replace('/{{\s*\$[a-zA-Z0-9_]+\s*}}/', '', $content);

        return preg_replace('/\$[a-zA-Z_][a-zA-Z0-9_]*/', '', $content);
    }

    /**
     * Get html ata for the `all_fields` placeholder
     *
     * @param array<string, mixed>|null $data
     * @return string
     */
    protected function getAllFieldsHtml(?array $data = null): string
    {
        if (empty($data ??= $this->getFormSubmissionData(true))) {
            return '';
        }

        /* @var Form $form */
        $form = $this->formSubmission->form;

        $allFieldsFormatted = array_map(function ($key, $value) use ($form) {
            $label = $form->getFormComponent()->findAttributeForKey($key);
            return "<p><b>{$label}</b>: {$value}</p>";
        }, array_keys($data), $data);

        return Blade::render(MailPanel::$view, [
            'slot' => implode('', $allFieldsFormatted),
        ]);
    }

    /**
     * @param array<string> $receivers
     * @return array<string>
     */
    protected function parseReceivers(array $receivers): array
    {
        return array_map(function (string $emailOrKey) {
            return ($value = Arr::get($this->getFormSubmissionData(), $emailOrKey))
                ? $value
                : $emailOrKey;
        }, $receivers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormSubmissionData(bool $withPlaceholders = false): array
    {
        $placeholders = $withPlaceholders
            ? $this->getPlaceholders()
            : [];

        return [
            ...$placeholders,
            ...$this->formSubmission->getFormattedData(
                formatKeys: false,
                data: $this->formSubmission->modifyDataValuesUsing($this->formSubmission->data),
            ),
            'submitter_email' => $this->formSubmission->submitter_email,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getPlaceholders(): array
    {
        $template = $this->formSubmission->form->template;

        try {
            if (is_subclass_of($template, FilamentForm::class)) {
                return $this->formSubmission->form->getFormComponent()->getPlaceholders();
            }
        } catch (\Exception) {
            return [];
        }

        return [];
    }
}
