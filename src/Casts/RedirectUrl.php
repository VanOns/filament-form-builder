<?php

namespace VanOns\FilamentFormBuilder\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores arrays as JSON and reads them back; plain strings pass through unchanged.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class RedirectUrl implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $first = $value[0];
        if ($first === '{' || $first === '[') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_array($value) ? json_encode($value) : $value;
    }
}
