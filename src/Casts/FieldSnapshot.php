<?php

namespace VanOns\FilamentFormBuilder\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the snapshot as lists, because MySQL sorts the keys of a JSON object
 * and the fields and their columns have to keep the order of the form.
 *
 * @implements CastsAttributes<array<string, array<string, mixed>>, array<string, array<string, mixed>>>
 */
class FieldSnapshot implements CastsAttributes
{
    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $snapshot = [];

        foreach (json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR) as $entry) {
            $field = $entry['key'];
            unset($entry['key']);
            $entry['columns'] = array_column($entry['columns'], 1, 0);
            $snapshot[$field] = $entry;
        }

        return $snapshot;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $stored = [];

        foreach ($value as $field => $entry) {
            $columns = [];

            foreach ($entry['columns'] as $column => $label) {
                $columns[] = [$column, $label];
            }

            $stored[] = ['key' => $field, ...$entry, 'columns' => $columns];
        }

        return json_encode($stored, JSON_THROW_ON_ERROR);
    }
}
