@use('Filament\Support\Icons\Heroicon')
@use('Illuminate\Support\Number')
@use('VanOns\FilamentFormBuilder\Classes\SubmissionFile')

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

                <span class="ffb-file-field">
                    <x-filament::icon :icon="Heroicon::PaperClip" class="ffb-file-field-icon" />
                    {{ $submission->findLabel($file->key) }}
                </span>

                <span class="ffb-file-badges">
                    @if ($size === null)
                        <x-filament::badge color="danger" size="sm">{{ __('filament-form-builder::general.submission.file_missing') }}</x-filament::badge>
                    @else
                        <x-filament::badge color="gray" size="sm">{{ Number::withLocale(app()->getLocale(), fn () => Number::fileSize($size, maxPrecision: 1)) }}</x-filament::badge>
                    @endif

                    @if ($file->extension() !== '')
                        <x-filament::badge :color="$file->isPdf() ? 'danger' : 'gray'" size="sm">{{ $file->extension() }}</x-filament::badge>
                    @endif
                </span>
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

    <p class="ffb-files-hint">
        {{ trans_choice('filament-form-builder::general.submission.files_hint', $days = SubmissionFile::linkDays(), ['days' => $days]) }}
    </p>
</div>
