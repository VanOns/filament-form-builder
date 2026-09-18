<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Mail\MailPanel;

class EmailNotification
{
    public string $subject;
    public string $content;
    public string $sender = '';
    public string $senderName = '';
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
        $this->senderName = $this->replacePlaceholders($notification['senderName'] ?? '');
        $this->receivers = $this->parseReceivers(
            $notification['receivers'] ?? []
        );
    }

    public function replacePlaceholders(string $content): string
    {
        // Only the mail renders every field as an HTML panel; the other
        // placeholders are shared with the confirmation query string.
        if (str_contains($content, '$all_fields') && ($allFields = $this->getAllFieldsHtml()) !== '') {
            $content = str_replace(['{{ $all_fields }}', '{{$all_fields}}'], $allFields, $content);
        }

        return SubmissionPlaceholders::make($this->formSubmission)->replace($content);
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
