{{--
    <x-share::buttons>: one button per platform. Web platforms go through the
    signed, counted redirect and open in a popup on desktop; app schemes and
    browser actions (copy, native) are counted by beacon. Behaviour:
    <x-share::scripts />. No title attributes: content blockers hide
    elements titled "Share on …"; aria-label names them instead.
--}}
@php
    $classes = ['share', 'share--'.$variant, 'share--'.$size, 'share--vertical' => $vertical, 'share--labels' => $labels];
@endphp
<div {{ $attributes->class($classes) }} role="group" aria-label="{{ $heading ?? __('share::share.heading') }}" data-share="{{ json_encode($share->client(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
    @if ($heading)
        <span class="share__heading">{{ $heading }}</span>
    @endif

    @foreach ($links as $link)
        @continue(in_array($link->key(), ['qr', 'embed'], true))
        @php
            $extra = ['class' => 'share__button share__button--'.$link->key()];
            if ($link->key() === 'native') {
                $extra['hidden'] = true;
            }
        @endphp
        @if ($link->href === null)
            <button type="button" {!! \Atannex\Share\Links\Link::attributeString($link->attributes() + $extra) !!}>
                {!! $link->icon !!}
                @if ($link->key() === 'copy')
                    <span class="share__done" aria-hidden="true">{!! \Atannex\Share\Support\Icons::render('check') !!}</span>
                @endif
                <span class="share__label">{{ $link->label }}</span>
            </button>
        @else
            <a {!! \Atannex\Share\Links\Link::attributeString($link->attributes() + $extra) !!}>
                {!! $link->icon !!}
                <span class="share__label">{{ $link->label }}</span>
            </a>
        @endif
    @endforeach

    @if ($sheet)
        <button type="button" class="share__button share__button--more" data-share-open="{{ $sheet }}" aria-haspopup="dialog" aria-controls="{{ $sheet }}" aria-label="{{ __('share::share.more') }}">
            {!! \Atannex\Share\Support\Icons::render('share') !!}
            <span class="share__label">{{ __('share::share.more') }}</span>
        </button>
    @endif

    <span class="share__status" role="status" aria-live="polite" data-share-status></span>
</div>
