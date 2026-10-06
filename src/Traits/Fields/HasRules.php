<?php

namespace VanOns\FilamentFormBuilder\Traits\Fields;

use VanOns\FilamentFormBuilder\Rules\RequiredWhenShown;

trait HasRules
{
    /**
     * @var array<int, mixed>
     */
    protected array $extraRules = [];

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->extraRules = [...$this->extraRules, ...$rules];

        return $this;
    }

    /**
     * @return array<mixed>
     */
    protected function getDefaultRules(): array
    {
        // An empty answer arrives as null, which rules like `email` or `in` would
        // reject. A visitor cannot fill in a field they never see either.
        if (! $this->isRequired() || $this->isHidden()) {
            return ['nullable'];
        }

        $conditions = $this->getConditions();

        return $conditions->isEmpty()
            ? ['required']
            : [new RequiredWhenShown($conditions), 'nullable'];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function fieldRules(): array
    {
        return $this->getDefaultRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        return $this->withExtraRules([$this->getKey() => $this->fieldRules()]);
    }

    /**
     * Adds the rules given in code to the field's own key, which comes first.
     *
     * @param  array<string, array<int|string, mixed>|null>  $rules
     * @return array<string, array<int|string, mixed>|null>
     */
    protected function withExtraRules(array $rules): array
    {
        $key = array_key_first($rules);

        if ($key !== null && $this->extraRules !== []) {
            $rules[$key] = [...($rules[$key] ?? []), ...$this->extraRules];
        }

        return array_map(fn (?array $fieldRules): ?array => empty($fieldRules) ? null : $fieldRules, $rules);
    }
}
