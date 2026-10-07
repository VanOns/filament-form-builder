<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\Mail\MailPanel;

class EmailNotification
{
    /**
     * A recipient that stands for the answer of a field, rather than a fixed
     * address.
     */
    public const FIELD_PREFIX = 'field:';

    public ?string $id;

    public bool $isEnabled;

    public string $subject;

    public string $content;

    public string $sender = '';

    public string $senderName = '';

    /**
     * @var list<string>
     */
    public array $receivers;

    public ?string $replyTo;

    public FieldConditions $conditions;

    /**
     * @var list<SubmissionFile>
     */
    public array $attachments;

    /**
     * @param  array<string, mixed>  $notification
     */
    public function __construct(
        public FormSubmission $formSubmission,
        array $notification,
    ) {
        $notification = static::normalize($notification);
        $values = SubmissionPlaceholders::make($formSubmission)->values();

        $this->id = $notification['id'];
        $this->isEnabled = $notification['enabled'];
        $this->subject = MergeTags::render($notification['subject'], $values, asText: true);
        $this->content = MergeTags::render($notification['content'], [...$values, ...$this->getHtmlValues($values)]);
        $this->sender = $notification['sender'] ?? '';
        $this->senderName = MergeTags::render($notification['senderName'], $values, asText: true);
        $this->receivers = $this->resolveAddresses($notification['to']);
        $this->replyTo = $this->resolveAddresses(array_filter([$notification['reply_to']]))[0] ?? null;
        $this->conditions = FieldConditions::fromArray($notification['conditions'], $notification['conditionMatch']);
        $this->attachments = $notification['attach_files'] ? $this->getAttachments() : [];
    }

    /**
     * A notification as stored now or before: `receivers` was a list of
     * addresses and field keys, and a notification had no id or switch.
     *
     * @param  array<string, mixed>  $notification
     * @return array{id: ?string, enabled: bool, subject: ?string, content: ?string, sender: ?string, senderName: ?string, to: list<string>, reply_to: ?string, conditions: array<mixed>, conditionMatch: string, attach_files: bool}
     */
    public static function normalize(array $notification): array
    {
        $recipients = $notification['to'] ?? $notification['receivers'] ?? [];

        return [
            'id' => is_string($notification['id'] ?? null) && $notification['id'] !== '' ? $notification['id'] : null,
            'enabled' => (bool) ($notification['enabled'] ?? true),
            'subject' => is_string($notification['subject'] ?? null) ? $notification['subject'] : null,
            'content' => is_string($notification['content'] ?? null) ? $notification['content'] : null,
            'sender' => is_string($notification['sender'] ?? null) && $notification['sender'] !== '' ? $notification['sender'] : null,
            'senderName' => is_string($notification['senderName'] ?? null) ? $notification['senderName'] : null,
            'to' => array_values(array_filter(array_map(static::toRecipient(...), is_array($recipients) ? $recipients : []))),
            'reply_to' => static::toRecipient($notification['reply_to'] ?? null),
            'conditions' => is_array($notification['conditions'] ?? null) ? array_values($notification['conditions']) : [],
            'conditionMatch' => ($notification['conditionMatch'] ?? null) === 'any' ? 'any' : 'all',
            'attach_files' => (bool) ($notification['attach_files'] ?? false),
        ];
    }

    /**
     * An address, or `field:key` for the answer of a field. A bare key, as
     * stored before, means that field.
     */
    public static function toRecipient(mixed $recipient): ?string
    {
        if (is_array($recipient)) {
            $recipient = $recipient['email'] ?? null;
        }

        if (!is_string($recipient) || trim($recipient) === '') {
            return null;
        }

        $recipient = trim($recipient);

        return str_contains($recipient, '@') || str_starts_with($recipient, static::FIELD_PREFIX)
            ? $recipient
            : static::FIELD_PREFIX . $recipient;
    }

    public function shouldSend(): bool
    {
        return $this->isEnabled
            && $this->receivers !== []
            && $this->conditions->passes($this->formSubmission->data ?? []);
    }

    /**
     * @param  array<int, string>  $recipients
     * @return list<string>
     */
    protected function resolveAddresses(array $recipients): array
    {
        $data = $this->formSubmission->data ?? [];
        $addresses = [];

        foreach ($recipients as $recipient) {
            $address = str_starts_with($recipient, static::FIELD_PREFIX)
                ? $data[substr($recipient, strlen(static::FIELD_PREFIX))] ?? null
                : $recipient;

            if (is_string($address) && filter_var(trim($address), FILTER_VALIDATE_EMAIL)) {
                $addresses[] = trim($address);
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * The uploads that fit within the attachment limit, in order. The rest
     * stay a link in the list of all fields.
     *
     * @return list<SubmissionFile>
     */
    protected function getAttachments(): array
    {
        $limit = (int) config('filament-form-builder.form-uploads-attach-max-size', 10240) * 1024;
        $attachments = [];
        $total = 0;

        foreach ($this->formSubmission->getFiles() as $files) {
            foreach ($files as $file) {
                $size = $file->size();

                if ($size !== null && $total + $size <= $limit) {
                    $attachments[] = $file;
                    $total += $size;
                }
            }
        }

        return $attachments;
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

    protected function getAllFieldsHtml(): string
    {
        $data = [
            ...SubmissionPlaceholders::fallbacks($this->formSubmission),
            ...$this->formSubmission->getFormattedData(),
        ];

        if ($data === []) {
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
}
