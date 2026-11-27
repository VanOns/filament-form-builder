<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Enums\SubmitNotificationType;

/**
 * One outcome of a submission: a message or a redirect, under conditions on
 * the answers. A form's outcomes are tried in order and the first that holds
 * wins; the last has no conditions.
 */
class SubmitNotification
{
    /**
     * @param  array<string, mixed>  $outcome
     * @return array{id: string, conditions: list<mixed>, conditionMatch: string, type: string, content: ?string, url: mixed, query: ?string}
     */
    public static function normalize(array $outcome): array
    {
        $type = SubmitNotificationType::tryFrom((string) ($outcome['type'] ?? '')) ?? SubmitNotificationType::Content;

        return [
            'id' => is_string($outcome['id'] ?? null) && $outcome['id'] !== '' ? $outcome['id'] : (string) Str::uuid(),
            'conditions' => is_array($outcome['conditions'] ?? null) ? array_values($outcome['conditions']) : [],
            'conditionMatch' => ($outcome['conditionMatch'] ?? null) === 'any' ? 'any' : 'all',
            'type' => $type->value,
            'content' => is_string($outcome['content'] ?? null) ? $outcome['content'] : null,
            'url' => $outcome['url'] ?? null,
            'query' => is_string($outcome['query'] ?? null) && $outcome['query'] !== '' ? $outcome['query'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  array<string, mixed>  $data
     */
    public static function holds(array $outcome, array $data): bool
    {
        return FieldConditions::fromArray($outcome['conditions'] ?? [], $outcome['conditionMatch'] ?? null)->passes($data);
    }
}
