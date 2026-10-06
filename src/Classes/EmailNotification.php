<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
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
        $values = SubmissionPlaceholders::make($formSubmission)->values();

        $this->subject = MergeTags::render($notification['subject'] ?? '', $values, asText: true);
        $this->content = MergeTags::render($notification['content'] ?? '', [...$values, ...$this->getHtmlValues($values)]);
        $this->sender = $notification['sender'] ?? '';
        $this->senderName = MergeTags::render($notification['senderName'] ?? '', $values, asText: true);
        $this->receivers = $this->parseReceivers(
            $notification['receivers'] ?? []
        );
    }

    /**
     * In the HTML of a mail, all fields are a panel and the submission's page
     * is a link.
     *
     * @param  array<string, string>  $values
     * @return array<string, HtmlString>
     */
    protected function getHtmlValues(array $values): array
    {
        $url = $values['submission_url'] ?? '';

        return [
            'all_fields' => new HtmlString($this->getAllFieldsHtml()),
            'submission_url' => new HtmlString($url === '' ? '' : '<a href="' . e($url) . '">' . e(__('filament-form-builder::general.merge_tags.view_submission')) . '</a>'),
        ];
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
