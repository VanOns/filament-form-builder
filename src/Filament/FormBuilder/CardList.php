<?php

namespace VanOns\FilamentFormBuilder\Filament\FormBuilder;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VanOns\FilamentFormBuilder\Models\Form;
use VanOns\FilamentFormBuilder\Models\FormSubmission;

/**
 * Items shown as cards, each edited in a slide-over. Stored as a list; while
 * the form is open, by id, so an action can name one.
 */
abstract class CardList extends Field
{
    /**
     * An item as the editor holds it, with an `id`.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    abstract public function prepare(array $item): array;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (CardList $component, ?array $rawState): void {
            $items = [];

            foreach ($rawState ?? [] as $item) {
                if (is_array($item)) {
                    $item = $component->prepare($item);
                    $items[$item['id']] = $item;
                }
            }

            $component->rawState($items);
        });

        $this->mutateDehydratedStateUsing(static fn (?array $state): array => array_values($state ?? []));
    }

    /**
     * Whether the list is saved right away; by default it goes with the form.
     *
     * @param  array<string, array<string, mixed>>  $items
     */
    public function store(array $items): bool
    {
        $this->rawState($items);

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function putItem(?string $id, array $data): bool
    {
        $items = $this->getRawState() ?? [];
        $id ??= (string) Str::uuid();
        $items[$id] = $this->prepare([...$items[$id] ?? [], ...$data, 'id' => $id]);

        return $this->store($items);
    }

    /**
     * An action that changes the list around one item.
     *
     * @param  Closure(array<string, array<string, mixed>>, string): array<string, array<string, mixed>>  $change
     */
    public function getChangeAction(string $name, Closure $change): Action
    {
        return Action::make($name)
            ->action(function (array $arguments, CardList $component) use ($change): void {
                $items = $component->getRawState() ?? [];
                $id = (string) ($arguments['item'] ?? '');

                if (isset($items[$id])) {
                    $component->store($change($items, $id));
                }
            });
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    public static function move(array $items, string $id, int $step): array
    {
        $ids = array_keys($items);
        $from = (int) array_search($id, $ids, true);
        $to = max(0, min(count($ids) - 1, $from + $step));

        array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, [$id]);

        return array_replace(array_flip($ids), $items);
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $item
     * @return array<string, array<string, mixed>>
     */
    public static function insertAfter(array $items, string $id, array $item): array
    {
        $position = (int) array_search($id, array_keys($items), true) + 1;

        return [
            ...array_slice($items, 0, $position, preserve_keys: true),
            $item['id'] => $item,
            ...array_slice($items, $position, preserve_keys: true),
        ];
    }

    public function getLatestSubmission(): ?FormSubmission
    {
        $record = $this->getRecord();

        return $record instanceof Form ? $record->submissions()->latest('id')->first()?->setRelation('form', $record) : null;
    }

    /**
     * Each merge tag of the form as the chip a card shows it as.
     *
     * @return array<string, HtmlString>
     */
    public static function getTagChips(Form $form): array
    {
        $tags = [];

        foreach ($form->getMergeTagGroups() as $group) {
            foreach ($group['tags'] as $id => $tag) {
                $icon = generate_icon_html($tag['icon'])?->toHtml() ?? '';
                $tags[$id] = new HtmlString('<span class="ffb-card-tag"><span class="ffb-card-tag-icon">' . $icon . '</span>' . e($tag['label']) . '</span>');
            }
        }

        return $tags;
    }
}
