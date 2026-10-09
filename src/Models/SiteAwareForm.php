<?php

namespace VanOns\FilamentFormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use VanOns\FilamentMultisite\Contracts\HasSites;
use VanOns\FilamentMultisite\Contracts\Multisitable;
use VanOns\FilamentMultisite\Observers\HasSitesObserver;
use VanOns\FilamentMultisite\Site;
use VanOns\FilamentMultisite\SiteRepository;

/*
 * A form can be a translation of another, like a page, only with
 * filament-multisite installed: the interface has to exist to implement it.
 * Even then nothing changes until `multisite` is turned on.
 */
if (interface_exists(Multisitable::class)) {
    abstract class SiteAwareForm extends Model implements Multisitable
    {
        use HasSites;

        /**
         * Everything an editor writes differs per site; the type and how long
         * submissions are kept follow the origin.
         *
         * @var list<string>
         */
        protected array $translates = ['title', 'custom', 'notifications', 'integrations', 'settings', 'submit_notifications'];

        // Without `multisite` a form need not have the columns the observer writes to.
        public static function bootHasSites(): void
        {
            if (static::usesSites()) {
                static::whenBooted(fn () => static::observe(HasSitesObserver::class));
            }
        }

        public static function usesSites(): bool
        {
            return config('filament-form-builder.multisite') === true;
        }

        public static function ensureSiteColumns(): void
        {
            if (static::usesSites() && ! Schema::hasColumns(static::query()->getModel()->getTable(), ['site', 'origin_id'])) {
                throw new RuntimeException('Forms per site need the columns `site` and `origin_id`: run `php artisan vendor:publish --tag=filament-form-builder-multisite-migrations` and `php artisan migrate`, or turn `multisite` off in config/filament-form-builder.php.');
            }
        }

        // Read from the attributes: on a new form Eloquent would take `site` for the relation site().
        public function site(): ?Site
        {
            return app(SiteRepository::class)->get($this->getSiteValue());
        }

        public function getSiteValue(): string
        {
            return (string) ($this->getAttributeFromArray($this->getSiteKey()) ?? 'default');
        }

        /**
         * A copy for another site keeps the title of its origin with the site
         * after it, as titles are unique.
         *
         * @param  array<string, mixed>  $attributes
         * @return array<string, mixed>
         */
        public function modifyClonedAttributes(array $attributes, Multisitable $origin, string $site): array
        {
            $name = app(SiteRepository::class)->get($site)->short_name ?? $site;
            $title = "{$attributes['title']} ({$name})";

            for ($number = 2; static::query()->withoutGlobalScopes()->where('title', $title)->exists(); $number++) {
                $title = "{$attributes['title']} ({$name} {$number})";
            }

            return [...$attributes, 'title' => $title];
        }

        /**
         * The form as the current site has it, or this one when that site has
         * none yet.
         */
        public function inCurrentSite(): static
        {
            if (! static::usesSites()) {
                return $this;
            }

            return $this->findInSite(app(SiteRepository::class)->current()->handle) ?? $this;
        }

        /**
         * The id of the form's copy on another site, or the id itself when that
         * site has none; for a page copied to another site.
         */
        public static function idInSite(mixed $id, string $site): mixed
        {
            if (! static::usesSites() || blank($id)) {
                return $id;
            }

            return static::query()->find($id)?->findInSite($site)?->getKey() ?? $id;
        }
    }
} else {
    abstract class SiteAwareForm extends Model
    {
        public static function usesSites(): bool
        {
            return false;
        }

        public static function ensureSiteColumns(): void
        {
        }

        public function inCurrentSite(): static
        {
            return $this;
        }

        public static function idInSite(mixed $id, string $site): mixed
        {
            return $id;
        }
    }
}
