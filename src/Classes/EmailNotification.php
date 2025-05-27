<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use ReflectionClass;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;
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
        $this->sender = $notification['sender'] ?? '';
        $this->receivers = $this->parseReceivers(
            $notification['receivers'] ?? []
        );
    }

    public function replacePlaceholders(string $content): string
    {
        $formData = $this->getFormSubmissionData(true);

        $data = array_filter([
            'allFields' => str_contains($content, '$allFields')
                ? $this->getAllFieldsHtml($formData)
                : null,
            ...$formData,
        ]);

        foreach ($data as $key => $value) {
            $search = '$' . $key;
            $content = str_replace("{{ {$search} }}", $value, $content);
            $content = str_replace("{{{$search}}}", $value, $content);
        }

        return $content;
    }

    /**
     * Get html ata for the `allFields` placeholder
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
            $label = $this->formSubmission->form->template::findAttributeForKey($key);
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
    protected function getFormSubmissionData(bool $withFallbacks = false): array
    {
        $fallbacks = $withFallbacks
            ? $this->getPlaceholders()
            : [];

        return [
            ...$fallbacks,
            ...$this->formSubmission->getFormattedData(),
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
            if ($template !== 'custom' && (new ReflectionClass($template))->isSubclassOf(FormComponent::class)) {
                /**
                 * @var FormComponent $template
                 */
                return $template::getPlaceholders();
            }
        } catch (\Exception) {
            return [];
        }

        return [];
    }
}
