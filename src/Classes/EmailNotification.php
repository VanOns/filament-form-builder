<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
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
        $this->content = $this->replaceContentPlaceholders($notification['content'] ?? '');
        $this->sender = $notification['sender'] ?? '';
        $this->senderName = $this->replacePlaceholders($notification['senderName'] ?? '');
        $this->receivers = $this->parseReceivers(
            $notification['receivers'] ?? []
        );
    }

    public function replacePlaceholders(string $content): string
    {
        return SubmissionPlaceholders::make($this->formSubmission)->replace($content, removeUnknown: true);
    }

    /**
     * The content is HTML, so an answer is escaped before it goes in. The panel
     * of all fields goes in last, so nothing typed into a field is read as a
     * placeholder.
     */
    public function replaceContentPlaceholders(string $content): string
    {
        $placeholders = SubmissionPlaceholders::make($this->formSubmission);

        $parts = array_map(
            fn (string $part): string => $placeholders->replace($part, e(...), removeUnknown: true),
            preg_split('/{{\s*\$all_fields\s*}}/', $content) ?: [$content],
        );

        return implode(count($parts) > 1 ? $this->getAllFieldsHtml() : '', $parts);
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

        $allFieldsFormatted = array_map(function ($key, $value) {
            $label = $this->formSubmission->findLabel((string) $key);

            return '<p><b>' . e($label) . '</b>: ' . e((string) $value) . '</p>';
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
            ? SubmissionPlaceholders::fallbacks($this->formSubmission)
            : [];

        return [
            ...$placeholders,
            ...$this->formSubmission->getFormattedData(),
        ];
    }
}
