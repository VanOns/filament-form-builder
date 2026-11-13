<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts what filament-form-builder v2 stored into what v3 reads: the canvas
 * fields, the template, the e-mail notifications and the `key_` prefix in
 * placeholders, plus the answers and uploads of v2 submissions.
 *
 * Runs after the package's upgrade migration. Anything already in the v3 shape
 * is left alone, so running it twice changes nothing.
 */
return new class () extends Migration {
    /**
     * v2 template class => v3 form type name, as registered in `types`.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'VanOns\FilamentFormBuilder\View\Components\Forms\CustomForm' => 'custom',
        'VanOns\FilamentFormBuilder\View\Components\Forms\ContactForm' => 'contact',
    ];

    /**
     * The v2 `columns` config, and the templates that overrode `columns()`.
     */
    public int $columns = 2;

    /**
     * @var array<string, int>
     */
    public array $templateColumns = [];

    /**
     * v2 field class => v3 field type name, as registered in `fields`. An
     * InputField and a SelectField get theirs from their settings.
     *
     * @var array<string, string>
     */
    public array $fieldTypes = [
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TitleField' => 'title',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextField' => 'text_block',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\TextAreaField' => 'textarea',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\CheckboxField' => 'checkbox',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FileUploadField' => 'file_upload',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\RecaptchaField' => 'recaptcha',
        'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SubmitField' => 'submit',
    ];

    public bool $convertSubmissions = true;

    private const INPUT_FIELD = 'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\InputField';

    private const SELECT_FIELD = 'VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\SelectField';

    private const INPUT_TYPES = ['text' => 'text', 'email' => 'email', 'tel' => 'phone', 'number' => 'number'];

    // The fields v2 had no width setting for; in v3 they fill the row.
    private const FULL_WIDTH = ['title', 'text_block', 'recaptcha', 'submit'];

    private const V2_ONLY = ['fieldType', 'inputType', 'large', 'column_start', 'set_key', 'visibleWhenKey', 'visibleWhenType', 'visibleWhenValue'];

    public function up(): void
    {
        $forms = DB::table('forms')->orderBy('id')->get();
        $this->ensureEverythingMaps($forms);

        foreach ($forms as $form) {
            $this->convertForm($form);
        }

        if ($this->convertSubmissions) {
            $this->convertSubmissions();
        }
    }

    public function down(): void
    {
        // v3 cannot read the v2 shape; restore a backup instead.
    }

    /**
     * Stops before anything changes when a template or field class has no v3
     * counterpart in the maps above.
     *
     * @param  iterable<object>  $forms
     */
    private function ensureEverythingMaps(iterable $forms): void
    {
        $missing = [];

        foreach ($forms as $form) {
            if (is_string($form->template) && str_contains($form->template, '\\') && !isset($this->templates[$form->template])) {
                $missing[] = "template {$form->template} (form {$form->id})";
            }

            foreach ($this->decode($form->custom)['fields'] ?? [] as $field) {
                $class = $field['fieldType'] ?? null;

                if (is_string($class) && !in_array($class, [self::INPUT_FIELD, self::SELECT_FIELD], true) && !isset($this->fieldTypes[$class])) {
                    $missing[] = "field {$class} (form {$form->id})";
                }
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Add these to $templates or $fieldTypes first: ' . implode(', ', array_unique($missing)));
        }
    }

    private function convertForm(object $form): void
    {
        $custom = $this->decode($form->custom);
        $notifications = $this->decode($form->notifications);
        $isV2 = (is_string($form->template) && isset($this->templates[$form->template]))
            || collect($custom['fields'] ?? [])->contains(fn (mixed $field): bool => isset($field['fieldType']))
            || collect($notifications)->contains(fn (mixed $notification): bool => isset($notification['receivers']));

        if (!$isV2) {
            return;
        }

        $columns = $this->templateColumns[$form->template] ?? $this->columns;
        $fields = array_map(fn (array $field): array => $this->convertField($field, $columns), $custom['fields'] ?? []);
        $emailKey = collect($fields)->firstWhere('type', 'email')['key'] ?? null;

        $outcomes = array_map(fn (array $outcome): array => [
            ...$outcome,
            'content' => $this->withoutPrefix($outcome['content'] ?? null, $emailKey),
            'query' => $this->withoutPrefix($outcome['query'] ?? null, $emailKey),
        ], $this->decode($form->submit_notifications));

        DB::table('forms')->where('id', $form->id)->update([
            'template' => $this->templates[$form->template] ?? $form->template,
            'custom' => $custom === [] ? $form->custom : json_encode([...$custom, 'fields' => $fields]),
            'notifications' => $notifications === [] ? $form->notifications : json_encode(array_map(fn (array $notification): array => $this->convertNotification($notification, $emailKey), $notifications)),
            'submit_notifications' => $outcomes === [] ? $form->submit_notifications : json_encode($outcomes),
        ]);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function convertField(array $field, int $columns): array
    {
        $class = $field['fieldType'] ?? null;

        if (!is_string($class)) {
            return $field;
        }

        $type = match ($class) {
            self::INPUT_FIELD => self::INPUT_TYPES[$field['inputType'] ?? 'text'] ?? 'text',
            self::SELECT_FIELD => ($field['multiple'] ?? false) ? 'checkbox_list' : 'radio',
            default => $this->fieldTypes[$class],
        };

        $converted = ['type' => $type, ...array_diff_key($field, array_flip(self::V2_ONLY))];

        if ($class === self::SELECT_FIELD) {
            unset($converted['multiple'], $converted['placeholder']);
        }

        if (in_array($type, self::FULL_WIDTH, true)) {
            unset($converted['column_span'], $converted['key']);
        } else {
            // The key v2 posted without its prefix: the one typed, or the label in snake case.
            $label = filled($field['label'] ?? null) ? $field['label'] : ucfirst(str_replace('-', ' ', Str::kebab(class_basename($class))));
            $converted['key'] = str_replace(['.', '*', ' ', '[', ']'], '', $this->stripPrefix(filled($field['key'] ?? null) ? $field['key'] : Str::snake($label)));
            $span = ($field['large'] ?? false) ? $columns : max(1, (int) ($field['column_span'] ?? 1));
            $converted['column_span'] = min(12, (int) round($span * 12 / $columns));
        }

        if (filled($field['visibleWhenKey'] ?? null)) {
            $converted['conditions'] = [[
                'key' => $this->stripPrefix($field['visibleWhenKey']),
                'operator' => $field['visibleWhenType'] ?? 'equals',
                'value' => $field['visibleWhenValue'] ?? null,
            ]];
            $converted['conditionMatch'] = 'all';
        }

        return $converted;
    }

    /**
     * @param  array<string, mixed>  $notification
     * @return array<string, mixed>
     */
    private function convertNotification(array $notification, ?string $emailKey): array
    {
        if (!array_key_exists('receivers', $notification)) {
            return $notification;
        }

        // v2 guessed the sender's address from the answers; v3 points at the field.
        $to = array_map(fn (mixed $receiver): ?string => match (true) {
            !is_string($receiver) || trim($receiver) === '' => null,
            str_contains($receiver, '@') => trim($receiver),
            $receiver === 'submitter_email' => $emailKey !== null ? "field:{$emailKey}" : null,
            default => 'field:' . $this->stripPrefix(trim($receiver)),
        }, (array) $notification['receivers']);

        return [
            'id' => (string) Str::uuid(),
            'enabled' => true,
            'subject' => $this->withoutPrefix($notification['subject'] ?? null, $emailKey),
            'content' => $this->withoutPrefix($notification['content'] ?? null, $emailKey),
            'sender' => $notification['sender'] ?? null,
            'senderName' => $this->withoutPrefix($notification['senderName'] ?? null, $emailKey),
            'to' => array_values(array_unique(array_filter($to))),
            'cc' => [],
            'bcc' => [],
            'reply_to' => null,
            'conditions' => [],
            'conditionMatch' => 'all',
            'attach_files' => false,
        ];
    }

    /**
     * Renames the `key_` answers and moves upload links into `files`, for
     * submissions from before v3: those have no snapshot of their form.
     */
    private function convertSubmissions(): void
    {
        DB::table('form_submissions')->whereNull('field_snapshot')->orderBy('id')->each(function (object $submission): void {
            $data = [];
            $files = $this->decode($submission->files);

            foreach ($this->decode($submission->data) as $key => $value) {
                $key = $this->stripPrefix((string) $key);
                $uploads = $this->uploads($value);

                if ($uploads !== null) {
                    $files[$key] ??= $uploads;
                } else {
                    $data[$key] ??= $value;
                }
            }

            DB::table('form_submissions')->where('id', $submission->id)->update([
                'data' => json_encode($data),
                'files' => $files === [] ? null : json_encode($files),
            ]);
        });
    }

    /**
     * A v2 upload was stored as a link to its download route, one or a list.
     *
     * @return list<array{path: string, name: string}>|null
     */
    private function uploads(mixed $value): ?array
    {
        $links = is_array($value) ? $value : [$value];
        $uploads = [];

        foreach ($links as $link) {
            if (!is_string($link) || !preg_match('#/filament-form-builder/file/([^?\#]+)#', $link, $match)) {
                return null;
            }

            $path = rawurldecode($match[1]);
            $uploads[] = ['path' => $path, 'name' => basename($path)];
        }

        return $uploads === [] ? null : $uploads;
    }

    private function withoutPrefix(?string $content, ?string $emailKey): ?string
    {
        if ($content === null) {
            return null;
        }

        return preg_replace_callback('/{{\s*\$([^\s{}]+)\s*}}/', function (array $match) use ($emailKey): string {
            $key = $match[1] === 'submitter_email' && $emailKey !== null ? $emailKey : $this->stripPrefix($match[1]);

            return '{{ $' . $key . ' }}';
        }, $content);
    }

    private function stripPrefix(string $key): string
    {
        return str_starts_with($key, 'key_') ? substr($key, 4) : $key;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(mixed $json): array
    {
        $value = is_string($json) ? json_decode($json, true) : $json;

        return is_array($value) ? $value : [];
    }
};
