@use('Filament\Support\Icons\Heroicon')
@use('Illuminate\Support\Number')

<div class="ffb-files">
    @foreach ($files as $file)
        @php
            $size = $file->size();
        @endphp

        <div class="ffb-file">
            <span class="ffb-file-thumb">
                <x-filament::icon
                    :icon="match (true) {
                        $file->isImage() => Heroicon::OutlinedPhoto,
                        $file->isPdf() => Heroicon::OutlinedDocumentText,
                        default => Heroicon::OutlinedDocument,
                    }"
                />
            </span>

            <div class="ffb-file-body">
                <span class="ffb-file-name">{{ $file->name }}</span>

                @if ($size === null)
                    <span>
                        <x-filament::badge color="danger" size="sm">{{ __('filament-form-builder::general.submission.file_missing') }}</x-filament::badge>
                    </span>
                @else
                    <span class="ffb-file-meta">
                        {{ collect([Number::withLocale(app()->getLocale(), fn () => Number::fileSize($size, maxPrecision: 1)), $file->mimeType()])->filter()->implode(' · ') }}
                    </span>
                @endif
            </div>

            @if ($size !== null)
                <div class="ffb-file-actions">
                    @if ($file->opensInBrowser())
                        <x-filament::icon-button
                            tag="a"
                            :href="$file->url()"
                            target="_blank"
                            color="gray"
                            :icon="Heroicon::OutlinedEye"
                            :label="__('filament-form-builder::general.submission.view_file', ['name' => $file->name])"
                        />
                    @endif

                    <x-filament::icon-button
                        tag="a"
                        :href="$file->downloadUrl()"
                        color="gray"
                        :icon="Heroicon::OutlinedArrowDownTray"
                        :label="__('filament-form-builder::general.submission.download_file', ['name' => $file->name])"
                    />
                </div>
            @endif
        </div>
    @endforeach
</div>
