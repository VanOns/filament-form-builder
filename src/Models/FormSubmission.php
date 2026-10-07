<?php

namespace VanOns\FilamentFormBuilder\Models;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\SubmissionAnswer;
use VanOns\FilamentFormBuilder\Classes\SubmissionFile;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionForceDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionRestored;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\ChoiceField;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Helpers\FieldTypeHelper;

/**
 * @property int $id
 * @property int $form_id
 * @property array<string, mixed> $data
 * @property array<string, list<array{path: string, name: string}>>|null $files
 * @property array<string, array{label: string, type: ?string, columns: array<string, string>, options: array<string, string>}>|null $field_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ?Form $form
 */
class FormSubmission extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $resolvedValues = null;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'files' => 'array',
            'field_snapshot' => 'array',
            'integrations' => 'array',
        ];
    }

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => FormSubmissionCreated::class,
        'updated' => FormSubmissionUpdated::class,
        'deleted' => FormSubmissionDeleted::class,
        'restored' => FormSubmissionRestored::class,
        'forceDeleted' => FormSubmissionForceDeleted::class,
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return HasMany<FormSubmissionNotificationLog, $this>
     */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(FormSubmissionNotificationLog::class);
    }

    /**
     * @return array<string, list<SubmissionFile>>
     */
    public function getFiles(): array
    {
        $files = [];

        foreach ($this->files ?? [] as $key => $stored) {
            foreach ($stored as $index => $file) {
                $files[$key][] = new SubmissionFile($this, (string) $key, $index, $file['path'], $file['name']);
            }
        }

        return $files;
    }

    public static function getFilesDisk(): string
    {
        return config('filament-form-builder.uploads.disk', 'local');
    }

    public function deleteFiles(): void
    {
        $paths = collect($this->files ?? [])->flatten(1)->pluck('path')->filter()->all();

        if ($paths !== []) {
            Storage::disk(static::getFilesDisk())->delete($paths);
        }
    }

    /**
     * Every answer the way it is shown, under its field key: a choice by its
     * label, a file as its download link, then the form type's own formatting.
     * The table, the detail page, the export and the mails all read from here.
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        if ($this->resolvedValues !== null) {
            return $this->resolvedValues;
        }

        $values = [...$this->data ?? [], ...$this->getFiles()];

        foreach ($this->form?->getFields() ?? [] as $field) {
            foreach (array_keys($field->getSubmissionColumns()) as $key) {
                if (array_key_exists($key, $values)) {
                    $values[$key] = $field->formatSubmissionValue($values[$key]);
                }
            }

            if (static::toText($values[$field->getKey()] ?? null) !== null) {
                $values[$field->getKey()] = $field->formatAnswer($values[$field->getKey()], $this);
            }
        }

        // A choice the form no longer has still reads by the label it had.
        $current = $this->form?->getSubmissionFields() ?? [];

        foreach ($this->field_snapshot ?? [] as $snapshot) {
            if ($snapshot['options'] === []) {
                continue;
            }

            foreach (array_diff_key(array_intersect_key($values, $snapshot['columns']), $current) as $key => $value) {
                $values[$key] = ChoiceField::toLabels($value, $snapshot['options']);
            }
        }

        if ($this->form !== null) {
            $values = $this->form->getType()->formatValues($values, $this);
        }

        return $this->resolvedValues = $values;
    }

    /**
     * A file reads as its download link, or as its name where the link would
     * only clutter the screen.
     */
    public function getDisplayText(string $key, bool $fileNames = false): ?string
    {
        return static::toText($this->getValues()[$key] ?? null, $fileNames);
    }

    public static function toText(mixed $value, bool $fileNames = false): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(
                fn (mixed $item): string => $fileNames && $item instanceof SubmissionFile ? $item->name : (string) $item,
                Arr::flatten($value),
            ));
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * The answers as text, without the empty ones.
     *
     * @return array<string, string>
     */
    public function getFormattedData(bool $formatKeys = false): array
    {
        $texts = [];

        foreach ($this->getValues() as $key => $value) {
            if (($text = static::toText($value)) !== null) {
                $texts[$formatKeys ? $this->findLabel((string) $key) : $key] = $text;
            }
        }

        return $texts;
    }

    /**
     * The answers for the detail page: one per field of the form as it is now,
     * and apart from those, what the submission holds for fields since removed.
     *
     * @return array{current: list<SubmissionAnswer>, removed: list<SubmissionAnswer>}
     */
    public function getAnswers(): array
    {
        $current = [];

        foreach ($this->form?->getFields(inputsOnly: true) ?? [] as $field) {
            $columns = $field->getSubmissionColumns();

            $current[] = $this->makeAnswer(
                $field->getKey(),
                $field->getLabel(),
                $columns,
                $field::icon(),
                view: $field->getAnswerView(),
                field: $field,
                badge: $field->isHidden() ? __('filament-form-builder::general.submission.hidden_field') : null,
                note: count($columns) > 1 ? __('filament-form-builder::general.submission.columns', ['count' => count($columns)]) : null,
            );
        }

        foreach ($this->form?->getType()->extraValues() ?? [] as $key => $label) {
            $current[] = $this->makeAnswer($key, $label, [$key => $label], Heroicon::OutlinedCube);
        }

        return ['current' => array_values(array_filter($current)), 'removed' => $this->getRemovedAnswers()];
    }

    /**
     * What the submission holds for fields the form no longer has, under the
     * label they had, or under its key where nothing kept one.
     *
     * @return list<SubmissionAnswer>
     */
    public function getRemovedAnswers(): array
    {
        $handled = [...$this->form?->getSubmissionFields() ?? [], ...$this->files ?? []];
        $removed = [];

        foreach ($this->field_snapshot ?? [] as $key => $snapshot) {
            $columns = array_diff_key($snapshot['columns'], $handled);

            if ($columns === []) {
                continue;
            }

            $handled += $columns;
            $type = FieldTypeHelper::resolve($snapshot['type'])
                ?? (is_string($snapshot['type']) && is_a($snapshot['type'], FormField::class, true) ? $snapshot['type'] : null);

            $removed[] = $this->makeAnswer(
                (string) $key,
                $snapshot['label'],
                $columns,
                $type !== null ? $type::icon() : Heroicon::OutlinedQuestionMarkCircle,
                badge: __('filament-form-builder::general.submission.removed_field'),
                note: $type !== null ? __('filament-form-builder::general.submission.was', ['type' => mb_strtolower($type::getTypeLabel())]) : null,
            );
        }

        foreach (array_keys(array_diff_key($this->getValues(), $handled)) as $key) {
            $label = Str::headline((string) $key);

            $removed[] = $this->makeAnswer(
                (string) $key,
                $label,
                [$key => $label],
                Heroicon::OutlinedQuestionMarkCircle,
                badge: __('filament-form-builder::general.submission.unknown'),
                note: __('filament-form-builder::general.submission.no_label'),
            );
        }

        return array_values(array_filter($removed));
    }

    /**
     * One answer for a field and every value it holds, or null when it holds
     * nothing. Files are left to a section of their own.
     *
     * @param  array<string, string>  $columns
     */
    protected function makeAnswer(
        string $key,
        string $label,
        array $columns,
        string | BackedEnum $icon,
        ?string $view = null,
        ?FormField $field = null,
        ?string $badge = null,
        ?string $note = null,
    ): ?SubmissionAnswer {
        $answered = array_filter(
            array_diff_key(array_intersect_key($this->getValues(), $columns), $this->files ?? []),
            fn (mixed $value): bool => static::toText($value) !== null,
        );

        if ($answered === []) {
            return null;
        }

        $isGrouped = count($columns) > 1;
        $raw = $this->data ?? [];

        return new SubmissionAnswer(
            key: $key,
            label: $label,
            value: $isGrouped ? $answered : reset($answered),
            raw: $isGrouped ? array_intersect_key($raw, $columns) : ($raw[$key] ?? null),
            icon: $icon,
            view: $view ?? ($isGrouped ? 'filament-form-builder::answers.columns' : 'filament-form-builder::answers.text'),
            field: $field,
            badge: $badge,
            note: $note,
            columns: $isGrouped ? $columns : [],
        );
    }

    /**
     * The label a key has now, or the one it had when this was submitted.
     */
    public function findLabel(string $key): string
    {
        if (($label = $this->form?->getSubmissionFields()[$key] ?? null) !== null) {
            return $label;
        }

        foreach ($this->field_snapshot ?? [] as $snapshot) {
            if (isset($snapshot['columns'][$key])) {
                return $snapshot['columns'][$key];
            }
        }

        return Str::headline($key);
    }

    /**
     * @return array<EmailNotification>
     */
    public function getNotifications(): array
    {
        return array_map(
            fn (array $notification) => new EmailNotification(
                formSubmission: $this,
                notification: $notification
            ),
            $this->form->notifications ?? [],
        );
    }

    /**
     * @return array<Integration>
 */
    public function getIntegrations(): array
    {
        return array_map(
            fn (array $integration) => Integration::fromArray($this, $integration),
            $this->form->integrations ?? [],
        );
    }

    protected static function boot(): void
    {
        parent::boot();

        // A soft deleted submission can come back, so its files stay until then.
        static::forceDeleted(fn (FormSubmission $submission) => $submission->deleteFiles());

        static::creating(function (FormSubmission $submission): void {
            $submission->field_snapshot ??= $submission->form?->getFieldSnapshot();
        });
    }
}
