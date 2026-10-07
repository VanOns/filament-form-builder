<?php

namespace VanOns\FilamentFormBuilder\Classes;

use VanOns\FilamentFormBuilder\Enums\ConditionOperator;

class FieldConditions
{
    /**
     * @param  list<array{key: string, operator: ConditionOperator, value: ?string}>  $rules
     */
    public function __construct(
        public readonly array $rules,
        public readonly bool $matchAll = true,
    ) {
    }

    /**
     * @param  array<mixed>  $conditions
     */
    public static function fromArray(array $conditions, ?string $match): self
    {
        $rules = [];

        foreach ($conditions as $condition) {
            $operator = is_array($condition) ? ConditionOperator::tryFrom((string) ($condition['operator'] ?? '')) : null;

            if ($operator !== null && is_string($condition['key'] ?? null) && $condition['key'] !== '') {
                $value = $condition['value'] ?? null;
                $rules[] = ['key' => $condition['key'], 'operator' => $operator, 'value' => is_scalar($value) ? (string) $value : null];
            }
        }

        return new self($rules, $match !== 'any');
    }

    /**
     * @param  array<mixed>  $conditions
     * @return array<mixed>
     */
    public static function renameKey(array $conditions, string $from, string $to): array
    {
        return array_map(fn (mixed $rule): mixed => is_array($rule) && ($rule['key'] ?? null) === $from ? [...$rule, 'key' => $to] : $rule, $conditions);
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function passes(array $data): bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        foreach ($this->rules as $rule) {
            $matches = $rule['operator']->matches($data[$rule['key']] ?? null, $rule['value']);

            if ($matches !== $this->matchAll) {
                return $matches;
            }
        }

        return $this->matchAll;
    }

    /**
     * @return array{match: string, rules: list<array{key: string, operator: string, value: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'match' => $this->matchAll ? 'all' : 'any',
            'rules' => array_map(fn (array $rule): array => [...$rule, 'operator' => $rule['operator']->value], $this->rules),
        ];
    }
}
