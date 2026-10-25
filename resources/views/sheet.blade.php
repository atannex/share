{{--
    <x-share::sheet>: the full share dialog. Opened by [data-share-open="{id}"].
--}}
@php
    $titleId = $id.'-title';
    $host = $data->url ? parse_url($data->url, PHP_URL_HOST) : null;
@endphp
<dialog id="{{ $id }}" {{ $attributes->class(['share-sheet']) }} aria-labelledby="{{ $titleId }}" data-share="{{ json_encode($share->client(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}" data-share-sheet>
    <div class="share-sheet__panel">
        <header class="share-sheet__header">
            <h2 id="{{ $titleId }}" class="share-sheet__title">{{ $heading ?? __('share::share.heading') }}</h2>
            <button type="button" class="share-sheet__close" data-share-close aria-label="{{ __('share::share.close') }}">{!! \Atannex\Share\Support\Icons::render('close') !!}</button>
        </header>

        <div class="share-sheet__body">
            @if ($data->title || $data->image)
                <figure class="share-sheet__card">
                    @if ($data->image)
                        <img src="{{ $data->image }}" alt="" width="120" height="63" loading="lazy" decoding="async">
                    @endif
                    <figcaption>
                        @if ($host)
                            <span class="share-sheet__host">{{ $host }}</span>
                        @endif
                        <span class="share-sheet__name">{{ \Atannex\Share\Support\Text::limit((string) ($data->title ?? $data->message()), 140) }}</span>
                    </figcaption>
                </figure>
            @endif

            <ul class="share-sheet__grid" aria-label="{{ __('share::share.platforms') }}">
                @foreach ($platforms as $link)
                    <li @if ($link->platform->mobile) data-share-mobile @endif>
                        @if ($link->href === null)
                            <button type="button" {!! \Atannex\Share\Links\Link::attributeString($link->attributes() + ['class' => 'share-sheet__platform']) !!}>
                                <span class="share-sheet__mark">{!! $link->icon !!}</span>
                                <span class="share-sheet__label">{{ $link->label }}</span>
                            </button>
                        @else
                            <a {!! \Atannex\Share\Links\Link::attributeString($link->attributes() + ['class' => 'share-sheet__platform']) !!}>
                                <span class="share-sheet__mark">{!! $link->icon !!}</span>
                                <span class="share-sheet__label">{{ $link->label }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($copy)
                <div class="share-sheet__field">
                    <label for="{{ $id }}-link">{{ $data->url ? __('share::share.link') : __('share::share.text') }}</label>
                    <div class="share-sheet__copy">
                        <input id="{{ $id }}-link" type="text" readonly value="{{ $copy->value }}" data-share-select>
                        <button type="button" {!! \Atannex\Share\Links\Link::attributeString($copy->attributes() + ['class' => 'share-sheet__copy-button']) !!}>
                            {!! $copy->icon !!}
                            <span class="share__done" aria-hidden="true">{!! \Atannex\Share\Support\Icons::render('check') !!}</span>
                            <span>{{ __('share::share.copy') }}</span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($qr)
                <details class="share-sheet__qr" data-share-platform="qr" data-share-beacon>
                    <summary>{!! \Atannex\Share\Support\Icons::render('qr') !!}<span>{{ __('share::share.qr') }}</span></summary>
                    <div class="share-sheet__qr-code">{!! $qr !!}</div>
                    <p>{{ __('share::share.qr_hint') }}</p>
                </details>
            @endif

            @if ($embed)
                <div class="share-sheet__field">
                    <label for="{{ $id }}-embed">{{ __('share::share.embed') }}</label>
                    <textarea id="{{ $id }}-embed" readonly rows="3" data-share-select>{{ $embed->value }}</textarea>
                    <button type="button" {!! \Atannex\Share\Links\Link::attributeString($embed->attributes() + ['class' => 'share-sheet__copy-button']) !!}>
                        {!! $embed->icon !!}
                        <span class="share__done" aria-hidden="true">{!! \Atannex\Share\Support\Icons::render('check') !!}</span>
                        <span>{{ __('share::share.copy_embed') }}</span>
                    </button>
                </div>
            @endif

            @if ($native && $data->files)
                {{-- Shown by the script only where the device can share these files. --}}
                <button type="button" hidden data-share-files {!! \Atannex\Share\Links\Link::attributeString($native->attributes() + ['class' => 'share-sheet__native']) !!}>
                    {!! $native->icon !!}<span>{{ __('share::share.share_file') }}</span>
                </button>
            @endif

            @if ($native)
                <button type="button" hidden {!! \Atannex\Share\Links\Link::attributeString($native->attributes() + ['class' => 'share-sheet__native']) !!}>
                    {!! $native->icon !!}<span>{{ __('share::share.native') }}</span>
                </button>
            @endif
        </div>
    </div>

    <span class="share__status" role="status" aria-live="polite" data-share-status></span>
</dialog>
