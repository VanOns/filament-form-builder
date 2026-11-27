@use('Filament\Support\Facades\FilamentAsset')
@use('Filament\Support\Icons\Heroicon')

<div
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('merge-tag-picker', 'van-ons/filament-form-builder') }}"
    x-data="mergeTagPickerComponent()"
    x-on:ffb-merge-tags.window="open($event.detail)"
    x-on:keydown.escape.window="isOpen && close()"
    x-on:click.outside="isOpen && ! anchor?.contains($event.target) && close()"
    x-show="isOpen"
    x-cloak
    x-bind:style="position ? { top: `${position.top}px`, left: `${position.left}px` } : { visibility: 'hidden' }"
    role="dialog"
    aria-label="{{ __('filament-forms::components.rich_editor.tools.merge_tags') }}"
    class="ffb-merge-tag-picker"
>
    <label class="ffb-merge-tag-picker-search">
        <x-filament::icon :icon="Heroicon::MagnifyingGlass" class="ffb-merge-tag-picker-search-icon" />

        <input
            x-ref="search"
            x-model="search"
            x-on:input="active = 0"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="choose()"
            type="text"
            placeholder="{{ __('filament-form-builder::general.merge_tags.search') }}"
        />
    </label>

    <div x-ref="list" class="ffb-merge-tag-picker-list">
        <template x-for="group in filtered" x-bind:key="group.label">
            <div class="ffb-merge-tag-picker-group">
                <p x-text="group.label" class="ffb-merge-tag-picker-group-label"></p>

                <template x-for="tag in group.tags" x-bind:key="tag.id">
                    <button
                        type="button"
                        x-on:mouseenter="active = flat.indexOf(tag)"
                        x-on:click="choose(tag)"
                        x-bind:class="{ 'ffb-active': isActive(tag) }"
                        x-bind:title="`${tag.label} (${tag.id})`"
                        class="ffb-merge-tag-picker-item"
                    >
                        <span x-html="icons[tag.icon] ?? ''" class="ffb-merge-tag-picker-icon"></span>
                        <span x-text="tag.label" class="ffb-merge-tag-picker-label"></span>
                        <code x-text="tag.id" class="ffb-merge-tag-picker-key"></code>
                    </button>
                </template>
            </div>
        </template>

        <p x-show="flat.length === 0" class="ffb-merge-tag-picker-empty">
            {{ __('filament-form-builder::general.merge_tags.no_results') }}
        </p>
    </div>
</div>
