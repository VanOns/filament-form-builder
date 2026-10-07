# Compatibility

For certain Filament versions, changes have to be made that render the package backwards incompatible with the previous version.
Please see the table below to determine which version you need.

| Version                                                               | Filament              | Laravel    | PHP  |
|-----------------------------------------------------------------------|-----------------------|------------|------|
| v3 (current)                                                          | \>=4.13 \| \>=5.8     | 11, 12, 13 | 8.2+ |
| [v2](https://github.com/VanOns/filament-form-builder/tree/release/v2) | \>=4.0 \| \>=5.0      | 11, 12, 13 | 8.2+ |
| [v1](https://github.com/VanOns/filament-form-builder/tree/release/v1) | <4.0                  |            |      |

Filament 4 runs on Livewire 3 and Filament 5 on Livewire 4; the package works with both. Laravel 13 needs PHP 8.3
or newer. v3 uses rich editor and table filter features that came with Filament 4.13 and 5.8.

**Please note:** the `main` branch will always be the latest major version.
