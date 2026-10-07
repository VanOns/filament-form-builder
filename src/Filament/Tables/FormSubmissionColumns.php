<?php

namespace VanOns\FilamentFormBuilder\Filament\Tables;

use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Filament\Tables\Filters\AnswerConstraints;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

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
            ...$this->fieldColumns(),
        ];
    }

    /**
     * @return array<int, QueryBuilder>
     */
    public function filters(): array
    {
        return [
            QueryBuilder::make()
                ->constraints($this->constraints())
                ->constraintPickerColumns(2)
                // Rules, or groups of rules joined by OR, but no OR inside an OR.
                ->maxNestingDepth(2),
        ];
    }

    /**
     * Every answer the form asks for, then the moment it was sent.
     *
     * @return array<string, Constraint>
     */
    public function constraints(): array
    {
        $constraints = [];

        foreach ($this->form->getFields(inputsOnly: true) as $field) {
            foreach ($field->getFilterConstraints() as $constraint) {
                $constraints[$constraint->getName()] = $constraint->icon($field::icon());
            }
        }

        foreach ($this->form->getType()->extraValues() as $key => $label) {
            $constraints[$key] ??= AnswerConstraints::text($key, $label)->icon(Heroicon::OutlinedCube);
        }

        // A field could be called created_at, so the date goes by another name.
        $constraints['submitted_at'] = DateConstraint::make('submitted_at')
            ->label(__('filament-form-builder::general.submission.submitted_at'))
            ->attribute('created_at');

        return $constraints;
    }

    /**
     * One column per field, hidden unless the field asks to be shown: a form of
     * twenty fields would otherwise open as an unreadable wall.
     *
     * @return array<int, TextColumn>
     */
    private function fieldColumns(): array
    {
        $columns = [];
        $shown = [];

        foreach ($this->form->getFields(inputsOnly: true) as $field) {
            if ($field->shouldShowColumn()) {
                $shown += $field->getSubmissionColumns();
            }
        }

        foreach ($this->form->getSubmissionFields() as $key => $label) {
            $columns[] = TextColumn::make("data.{$key}")
                ->label(Str::limit($label, 40))
                ->state(fn (FormSubmission $record): ?string => $record->getDisplayText($key, fileNames: true))
                ->toggleable(isToggledHiddenByDefault: !array_key_exists($key, $shown))
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

    private function path(string $key): string
    {
        return "data->{$key}";
    }
}
