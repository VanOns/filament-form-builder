<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Arr;
use ReflectionClass;
use VanOns\FilamentFormBuilder\Models\FormSubmission;
use VanOns\FilamentFormBuilder\View\Components\FormComponent;

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
        $data = $this->getFormSubmissionData(true);

        foreach ($data as $key => $value) {
            $search = '$' . $key;
            $content = str_replace("{{ {$search} }}", $value, $content);
            $content = str_replace("{{{$search}}}", $value, $content);
        }

        return $content;
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
