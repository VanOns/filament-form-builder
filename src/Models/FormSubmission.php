<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Classes\EmailNotification;
use VanOns\FilamentFormBuilder\Classes\Integration;
use VanOns\FilamentFormBuilder\Classes\SubmissionFile;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionCreated;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionForceDeleted;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionRestored;
use VanOns\FilamentFormBuilder\Events\FormSubmission\FormSubmissionUpdated;
use VanOns\FilamentFormBuilder\Helpers\TemplateHelper;

/**
 * @property int $id
 * @property int $form_id
 * @property ?string $submitter_email
 * @property array<string, mixed> $data
 * @property array<string, list<array{path: string, name: string}>>|null $files
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

    /**
     * Every answer the way it is shown, under its field key: a choice by its
     * label, a file as its download link, then the template's own formatting.
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
        }

        if ($template = TemplateHelper::resolve($this->form?->template)) {
            $values = $template::modifyDataValues($values, $this);
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
     * The answers under their labels for the detail page, which links the files
     * in a section of their own.
     *
     * @return array<string, string>
     */
    public function getDetailData(): array
    {
        $texts = [];

        foreach ($this->getValues() as $key => $value) {
            if (($text = static::toText($value, fileNames: true)) !== null) {
                $texts[$this->findLabel((string) $key)] = $text;
            }
        }

        if ($template = TemplateHelper::resolve($this->form?->template)) {
            return $template::modifyResourceDataUsing($texts, $this);
        }

        return $texts;
    }

    public function findLabel(string $key): string
    {
        return TemplateHelper::isTemplate($this->form?->template)
            ? $this->form->getFormComponent()->findAttributeForKey($key)
            : Str::headline($key);
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

        static::created(function (FormSubmission $submission) {
            if ($template = TemplateHelper::resolve($submission->form->template)) {
                $template::afterSubmissionCreated($submission);
            }
        });
    }
}
