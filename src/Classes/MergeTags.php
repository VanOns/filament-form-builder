<?php

namespace VanOns\FilamentFormBuilder\Classes;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Merge tags are Filament's rich editor nodes: `<span data-type="mergeTag"
 * data-id="voornaam">`. Content from before them holds `{{ $voornaam }}` as
 * text, which is read as the same tag.
 */
class MergeTags
{
    public const LEGACY = '/{{\s*\$([^\s{}]+)\s*}}/u';

    /**
     * Stored content as rich editor HTML with its `{{ $key }}` text turned into
     * merge tags. Plain text, such as a subject from before, becomes a
     * paragraph. Inside the attributes of a tag, such as a link, they stay text.
     */
    public static function fromLegacy(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return $content;
        }

        if (!str_starts_with(ltrim($content), '<')) {
            $content = '<p>' . e($content) . '</p>';
        }

        $parts = preg_split('/(<[^>]*>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$content];

        foreach ($parts as $index => $part) {
            if (!str_starts_with($part, '<')) {
                $parts[$index] = preg_replace_callback(
                    self::LEGACY,
                    fn (array $match): string => '<span data-type="mergeTag" data-id="' . e($match[1]) . '"></span>',
                    $part,
                ) ?? $part;
            }
        }

        return implode('', $parts);
    }

    /**
     * The tags in stored HTML or in the editor's state, in order of appearance.
     *
     * @return list<string>
     */
    public static function ids(mixed $content): array
    {
        $ids = [];

        if (is_array($content)) {
            if (($content['type'] ?? null) === 'mergeTag' && is_string($content['attrs']['id'] ?? null)) {
                $ids[] = $content['attrs']['id'];
            }

            foreach ($content as $child) {
                $ids = [...$ids, ...static::ids($child)];
            }
        } elseif (is_string($content)) {
            preg_match_all('/<span\b[^>]*\bdata-type="mergeTag"[^>]*>/', $content, $spans);

            foreach ($spans[0] as $span) {
                if (preg_match('/\bdata-id="([^"]*)"/', $span, $id)) {
                    $ids[] = html_entity_decode($id[1], ENT_QUOTES);
                }
            }

            preg_match_all(self::LEGACY, $content, $legacy);
            $ids = [...$ids, ...$legacy[1]];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fills in the tags. A tag without a value stays empty, and a value is
     * never read as a tag of its own.
     *
     * @param  array<string, string|Htmlable>  $values
     */
    public static function render(mixed $content, array $values, bool $asText = false): string
    {
        if (is_string($content)) {
            $content = static::fromLegacy($content);
        }

        if (blank($content)) {
            return '';
        }

        $renderer = RichContentRenderer::make(is_string($content) ? self::fillAttributes($content, $values) : $content)
            ->mergeTags([...array_fill_keys(static::ids($content), ''), ...$values]);

        if ($asText) {
            // The editor hands its text back with entities, such as &amp; for &.
            return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($renderer->toText(), ENT_QUOTES | ENT_HTML5)));
        }

        // The content is the editors' own HTML, as before, and every value goes
        // in as an escaped text node or as HTML built here, so it is not sanitized.
        return (string) preg_replace('/<span data-type="mergeTag"[^>]*>([^<]*)<\/span>/', '$1', $renderer->toUnsafeHtml());
    }

    /**
     * A `{{ $key }}` left inside an attribute, such as a link, gets its value
     * escaped for the attribute.
     *
     * @param  array<string, string|Htmlable>  $values
     */
    private static function fillAttributes(string $html, array $values): string
    {
        return (string) preg_replace_callback('/<[^>]*>/', fn (array $tag): string => (string) preg_replace_callback(
            self::LEGACY,
            fn (array $match): string => e(is_string($values[$match[1]] ?? null) ? $values[$match[1]] : ''),
            $tag[0],
        ), $html);
    }

    /**
     * Points every tag `$from` in stored HTML, legacy text or the editor's state
     * at `$to` instead.
     */
    public static function rename(mixed $value, string $from, string $to): mixed
    {
        if (is_array($value)) {
            if (($value['type'] ?? null) === 'mergeTag' && ($value['attrs']['id'] ?? null) === $from) {
                $value['attrs']['id'] = $to;
            }

            return array_map(fn (mixed $item): mixed => static::rename($item, $from, $to), $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = (string) preg_replace('/{{\s*\$' . preg_quote($from, '/') . '\s*}}/', '{{ $' . $to . ' }}', $value);

        return (string) preg_replace_callback(
            '/<span\b[^>]*\bdata-type="mergeTag"[^>]*>/',
            fn (array $span): string => str_replace('data-id="' . e($from) . '"', 'data-id="' . e($to) . '"', $span[0]),
            $value,
        );
    }
}
