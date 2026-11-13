<?php

namespace VanOns\FilamentFormBuilder\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentMultisite\Contracts\Multisitable;

/**
 * Picks a form, for a page block or anything else that shows one. With forms
 * per site it offers the forms as the site of the record it is on has them,
 * and turns a form of another site into this site's copy: a page copied to
 * another site picks up that site's form without code on the page model.
 */
class FormSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(trans_choice('filament-form-builder::general.models.form.label', 1));
        $this->searchable();
        $this->options(fn (FormSelect $component): array => $component->getFormOptions());

        $this->afterStateHydrated(function (FormSelect $component, mixed $state): void {
            $site = $component->getSite();

            if ($site !== null && filled($state)) {
                $component->state(Form::idInSite($state, $site));
            }
        });
    }

    /**
     * The site of the record the select is on, such as the page a block is in.
     */
    public function getSite(): ?string
    {
        $record = $this->getRecord();

        if (! Form::usesSites() || ! $record instanceof Multisitable) {
            return null;
        }

        // Not getAttribute(): `site` is also a method, and a record made without a site has not loaded the column's default.
        $key = $record->getSiteKey();
        $site = $record->getAttributes()[$key] ?? ($record->exists ? $record->newQuery()->whereKey($record->getKey())->value($key) : null);

        return $site === null ? null : (string) $site;
    }

    /**
     * @return array<int|string, string>
     */
    public function getFormOptions(): array
    {
        $site = $this->getSite();

        if ($site === null) {
            return Form::query()->orderBy('title')->pluck('title', 'id')->all();
        }

        // Per form and its copies: the one of this site, or the original while the site has none.
        return Form::query()
            ->get(['id', 'title', 'site', 'origin_id'])
            ->groupBy(fn (Form $form): int|string => $form->getAttribute('origin_id') ?? $form->getKey())
            ->map(fn (Collection $copies): Form => $copies->first(fn (Form $form): bool => $form->getAttribute('site') === $site)
                ?? $copies->first(fn (Form $form): bool => $form->getAttribute('origin_id') === null)
                ?? $copies->first())
            ->sortBy('title')
            ->pluck('title', 'id')
            ->all();
    }
}
