{{-- <x-share::meta>: what platforms show when the link is shared. --}}
@php
    $description = $share->text ?? ($share->message !== $share->title ? $share->message : null);
    $type = $share->type ?? ($share->video ? 'video.other' : 'website');
    $card = $share->video && $share->video['player'] ? 'player' : ($share->image ? 'summary_large_image' : 'summary');
@endphp
@if ($share->title)
<meta property="og:title" content="{{ $share->title }}">
<meta name="twitter:title" content="{{ $share->title }}">
@endif
@if ($description)
<meta property="og:description" content="{{ $description }}">
<meta name="twitter:description" content="{{ $description }}">
@endif
@if ($share->url)
<meta property="og:url" content="{{ $share->url }}">
@endif
<meta property="og:type" content="{{ $type }}">
@if ($share->siteName)
<meta property="og:site_name" content="{{ $share->siteName }}">
@endif
@if ($share->locale)
<meta property="og:locale" content="{{ $share->locale }}">
@endif
@if ($share->image)
<meta property="og:image" content="{{ $share->image }}">
@if (str_starts_with($share->image, 'https://'))
<meta property="og:image:secure_url" content="{{ $share->image }}">
@endif
@if ($share->imageWidth && $share->imageHeight)
<meta property="og:image:width" content="{{ $share->imageWidth }}">
<meta property="og:image:height" content="{{ $share->imageHeight }}">
@endif
@if ($share->imageAlt)
<meta property="og:image:alt" content="{{ $share->imageAlt }}">
<meta name="twitter:image:alt" content="{{ $share->imageAlt }}">
@endif
<meta name="twitter:image" content="{{ $share->image }}">
@endif
<meta name="twitter:card" content="{{ $card }}">
@if ($share->via)
<meta name="twitter:site" content="{{ '@'.$share->via }}">
@endif
@if ($share->video)
<meta property="og:video" content="{{ $share->video['url'] }}">
<meta property="og:video:secure_url" content="{{ $share->video['url'] }}">
<meta property="og:video:type" content="{{ $share->video['mime'] }}">
@if ($share->video['width'] && $share->video['height'])
<meta property="og:video:width" content="{{ $share->video['width'] }}">
<meta property="og:video:height" content="{{ $share->video['height'] }}">
@endif
@if ($share->video['player'])
<meta name="twitter:player" content="{{ $share->video['player'] }}">
<meta name="twitter:player:width" content="{{ $share->video['width'] ?? 1280 }}">
<meta name="twitter:player:height" content="{{ $share->video['height'] ?? 720 }}">
<meta name="twitter:player:stream" content="{{ $share->video['url'] }}">
@endif
@endif
@if ($type === 'article')
@foreach ($share->hashtags as $tag)
<meta property="article:tag" content="{{ $tag }}">
@endforeach
@endif
@foreach ($share->meta as $property => $content)
@if (str_contains($property, ':') && ! str_starts_with($property, 'twitter:'))
<meta property="{{ $property }}" content="{{ $content }}">
@else
<meta name="{{ $property }}" content="{{ $content }}">
@endif
@endforeach
