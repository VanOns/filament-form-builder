<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Filament\FormBuilder\Fields\FormField;
use VanOns\FilamentFormBuilder\Models\Form;

/**
 * Turns one form into table columns and filters over its submissions, so the
 * submissions page can show what people actually filled in rather than only
 * when they filled it in.
 */
class FormSubmissionColumns
{
    public function __construct(private readonly Form $form)
    {
    }

    public static function for(Form $form): self
    {
        return new self($form);
    }

    /**
     * @return array<int, TextColumn>
     */
    public function columns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label(__('filament-form-builder::general.created_at'))
                ->dateTime()
                ->sortable(),
            TextColumn::make('submitter_email')
                ->label(__('filament-form-builder::general.submitter_email'))
                ->sortable()
                ->searchable()
                ->toggleable(),
            ...$this->fieldColumns(),
        ];
    }

    /**
     * @return array<int, SelectFilter>
     */
    public function filters(): array
    {
        $filters = [];

        foreach ($this->form->getFields() as $field) {
            $options = $field->getFilterOptions();

            if ($options === []) {
                continue;
            }

            $key = $field->getKey();

            $filters[] = SelectFilter::make($key)
                ->label($field->getLabel())
                ->options($options)
                ->multiple()
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    $data['values'] ?? [],
                    fn (Builder $query, array $values) => $query->whereIn($this->path($key), $values),
                ));
        }

        return $filters;
    }

    /**
     * One column per field, hidden until someone asks for it: a form of twenty
     * fields would otherwise open as an unreadable wall.
     *
     * @return array<int, TextColumn>
     */
    private function fieldColumns(): array
    {
        $columns = [];

        $fields = $this->fieldsByKey();

        foreach ($this->form->getSubmissionFields() as $key => $label) {
            $field = $fields[$key] ?? null;

            $columns[] = TextColumn::make("data.{$key}")
                ->label(Str::limit($label, 40))
                ->formatStateUsing(fn (mixed $state): ?string => $this->format($field?->formatSubmissionValue($state) ?? $state))
                ->toggleable(isToggledHiddenByDefault: true)
                // Filament would look for a `data` column; these live inside it.
                ->sortable(query: function (Builder $query, string $direction) use ($key): Builder {
                    $query->orderBy($this->path($key), $direction);

                    return $query;
                })
                ->searchable(query: function (Builder $query, string $search) use ($key): Builder {
                    return $query->where($this->path($key), 'like', "%{$search}%");
                });
        }

        return $columns;
    }

    /**
     * @return array<string, FormField>
     */
    private function fieldsByKey(): array
    {
        $fields = [];

        foreach ($this->form->getFields() as $field) {
            foreach (array_keys($field->getSubmissionColumns()) as $key) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    private function path(string $key): string
    {
        return "data->{$key}";
    }

    private function format(mixed $state): ?string
    {
        if (is_array($state)) {
            return implode(', ', Arr::flatten($state));
        }

        return $state === null ? null : (string) $state;
    }
}
