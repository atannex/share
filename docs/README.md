# atannex/share

[![Tests](https://github.com/atannex/share/actions/workflows/tests.yml/badge.svg)](https://github.com/atannex/share/actions/workflows/tests.yml)

Share **anything** from Laravel to **38 platforms**: a model, a link, some text or a file. You get correctly encoded share links, posts shortened to each platform's limit, share counts that bots can't inflate, copy, native, QR and embed actions, ready-made Blade buttons and a share sheet, Open Graph tags, and a command that checks how your link preview will look.

```php
Share::for($post)->links();                         // every configured platform
Share::for($post)->to('whatsapp');                  // https://wa.me/?text=…
Share::url('https://example.com', 'Hello')->only('x', 'bluesky', 'copy');
Share::text('Just words, no link');                 // platforms that need a link are left out
return Share::for($post);                           // JSON for a SPA: data, links, client settings
```

```blade
<head> <x-share::meta :for="$post" /> <x-share::styles /> </head>

<x-share::buttons :for="$post" variant="brand" sheet="share-sheet" />
<x-share::sheet :for="$post" id="share-sheet" />
<x-share::scripts />
```

- [Why](#why)
- [Install](#install)
- [Sharing anything](#sharing-anything)
- [Platforms](#platforms)
- [Blade components](#blade-components)
- [Counting shares](#counting-shares)
- [Link previews](#link-previews)
- [Headless use](#headless-use)
- [Testing](#testing)
- [Configuration](#configuration)

## Why

Share buttons look simple, but they often break in small ways. This package handles those cases:

| Problem | What atannex/share does |
| --- | --- |
| A title with `&`, `#` or quotes cuts the shared text short | Every value is RFC 3986 encoded (`%20`, never `+`). Template parameters that render empty are left out. |
| A long title goes past X's 280 characters | Each platform has a `limit` and a `link_weight` (X counts any link as 23). The post is cut at a word boundary, and hashtags and `via @handle` are counted too. |
| Pinterest without an image, or Facebook without a link | Each platform declares what it `requires`. A share that lacks it simply skips that platform. |
| Share counts inflated by link-preview bots, prefetches and double clicks | Share links are signed redirects. Bots, `Sec-Purpose: prefetch`, HEAD requests and repeats within 30 minutes are not counted. |
| Anyone can use `/share?to=` to redirect readers elsewhere | Links are HMAC-signed, so an unsigned or tampered link returns 404. |
| Cached pages carry a stale CSRF token, so copy and native beacons fail | The beacon is signed instead of using CSRF. |
| No web share URL for Instagram, TikTok, Signal or AirDrop | The device's own share sheet (Web Share API) shares the link or the file itself, such as a video posted straight to Instagram. |
| Moving a link from desktop to phone | A QR code in the share sheet. |
| A broken preview card that nobody notices until after publishing | `php artisan share:inspect https://…` |

The package has no front-end build step and no dependencies. The script is about 12 KB (unminified) and is inlined with your CSP nonce. Every colour and size is a `--share-*` CSS variable.

## Install

```bash
composer require atannex/share
php artisan migrate                 # share_counts (skip with config share.migrations = false)
```

Optional:

```bash
composer require bacon/bacon-qr-code   # QR codes ("qr" platform, Share::qr())
php artisan vendor:publish --tag=share-config
```

## Sharing anything

`Share::for()` (also `share()` and `$model->share()`) accepts:

| You pass | It shares |
| --- | --- |
| a model with `#[ShareAs]` | the fields you mapped |
| a model without it | common fields: `title`/`name`/`headline`, `description`/`summary`/`excerpt`, `image`/`cover_url`/…, `url`/`permalink` |
| an object implementing `Shareable` | whatever its `toShare()` returns |
| a class registered with `Share::resolveUsing()` | what your resolver returns (for classes you can't change) |
| `'https://…'` or `'/path'` | that address |
| `'any other text'` | text only |
| an array, `Arrayable` or `JsonSerializable` | its fields (`url`, `title`, `message`, `text`, `image`, `hashtags`, `via`, `video`, `files`, `embed`) |
| a closure | what it returns |
| nothing | the current page |

### Models

```php
use Atannex\Share\Attributes\ShareAs;
use Atannex\Share\Concerns\HasShares;

#[ShareAs(route: 'posts.show', routeKey: 'slug', text: 'excerpt', image: 'cover_url', hashtags: 'tags', type: 'article')]
class Post extends Model
{
    use HasShares;
}

$post->share()->links();
$post->shareCount();                 // all platforms, all time
$post->shareCount('whatsapp', now()->subWeek());
$post->shareBreakdown();             // ['whatsapp' => 41, 'facebook' => 12, …]
Post::withShareCount()->orderByDesc('shares_count')->get();
```

Each `ShareAs` value can be an attribute, a method or a dot path (`author.name`). Use `url:` instead of `route:` when the model already knows its address.

For full control, implement the contract:

```php
class Fixture implements \Atannex\Share\Contracts\Shareable
{
    public function toShare(): array
    {
        return [
            'url' => route('matches.show', $this),
            'title' => "{$this->home} {$this->score} {$this->away}",
            'message' => "FT: {$this->home} {$this->score} {$this->away} ⚽",
            'image' => $this->cardImageUrl(),
            'hashtags' => [$this->league->name],
        ];
    }
}
```

### Fluent changes

Every value is cleaned up the same way: tags are stripped from HTML, entities are decoded, relative URLs become absolute, media objects (`getFullUrl()`/`getUrl()`) become their URLs, and hashtags become `#LandUse` (from "land use"), with duplicates dropped.

```php
Share::for($post)
    ->message('Breaking: the bridge is open 🎉')    // post text (default: title)
    ->text('Longer description for email and cards')
    ->image($media, alt: 'The new bridge', width: 1200, height: 630)
    ->hashtags('Lebialem', $post->tags)
    ->via('atannex')
    ->video($file, player: route('videos.embed', $video))   // og:video and X player card
    ->file($file->getFullUrl(), 'video/mp4', size: $file->size)   // native share of the file itself
    ->embed('<iframe …></iframe>')
    ->meta(['article:published_time' => $post->published_at->toIso8601String()])
    ->about($post)                                   // count shares against this model
    ->only('facebook', 'x', 'whatsapp')              // or ->except('reddit'), ->preset('compact')
    ->withoutTracking()                              // straight to the platform, not counted
    ->withoutUtm();
```

## Platforms

`php artisan share:platforms` lists the platforms, and `php artisan share:links https://… --title=…` prints the address each one builds.

| Kind | Platforms |
| --- | --- |
| Social | facebook, x (`twitter`), threads, bluesky, mastodon, fediverse (asks for the reader's instance), linkedin, reddit, pinterest, tumblr, hackernews, flipboard, buffer, xing, vk, odnoklassniki (`ok`), weibo, qzone, naver, hatena, snapchat |
| Messaging | whatsapp, telegram, line, messenger\*, viber\*, sms\* |
| Work and email | teams, classroom, trello, gmail, outlook, email (`mail`) |
| In the browser | copy (`link`), native, qr, embed, print |

\* Phone apps. The default stylesheet hides them on desktop.

Each platform is plain data in [`resources/platforms.php`](resources/platforms.php). Change one or add your own in config or at runtime:

```php
// config/share.php
'custom' => [
    'mastodon' => ['instance' => 'mas.to'],
    'intranet' => [
        'label' => 'Intranet',
        'url' => 'https://intra.example/share',
        'query' => ['u' => '{url}', 't' => '{title}'],
        'color' => '#0f172a',
        'icon' => '<svg …>',              // or 'fab fa-…', or a bundled icon key
    ],
],

// or in a service provider, with code:
Share::extend('shortener', [
    'label' => 'Short link',
    'build' => fn (ShareData $share) => 'https://s.example/new?u='.urlencode($share->url),
]);
```

Templates can use these placeholders: `{url}` `{title}` `{message}` `{text}` `{body}` (message, a blank line, link) `{mail_body}` `{image}` `{via}` `{hashtags}` `{hashtag_line}` `{first_hashtag}` `{tags}` `{site}`, plus any key of the platform's definition (such as `{instance}`).

## Blade components

| Component | Purpose |
| --- | --- |
| `<x-share::buttons>` | A row (or `vertical` column) of buttons. `variant`: `icons`, `pills`, `brand`, `minimal`. `size`: `sm`, `md`, `lg`. `sheet="id"` adds a "More" button. Also takes `heading` and `labels`. |
| `<x-share::sheet>` | A dialog with a preview card, every platform, the link to copy, a QR code, the embed code and the device's share sheet. On phones it opens as a bottom sheet. |
| `<x-share::meta>` | Open Graph and X card tags, including `og:video`, the player card and `article:tag`. |
| `<x-share::scripts>` | The inlined script, rendered once. Uses Vite's CSP nonce automatically, or pass `nonce="…"`. |
| `<x-share::styles>` | The default look. Leave it out to style everything yourself. |

Every component accepts `:for="$anything"` or `:share="Share::for(…)"`, plus `only`, `except` and `preset`. All labels can be translated (English and French are included; publish with `--tag=share-lang`).

Theme with variables:

```css
:root { --share-size: 2.75rem; --share-radius: .75rem; --share-focus: #c2410c; }
```

Any element can open a sheet with `data-share-open="share-sheet"`, or `data-share-open="share-sheet#section-id"` to open it and scroll to a section. A native button with `data-share-files` shares the share's files (added with `->file()`), and the script only shows it where the device can share them. The sheet adds one automatically.

The script fires a `share:shared` event for your own analytics:

```js
document.addEventListener('share:shared', (e) => gtag('event', 'share', { method: e.detail.platform }));
```

## Counting shares

- **Web platforms** go through `GET /share/{platform}?to=…&signature=…`, which counts the share and redirects.
- **App schemes, email and browser actions** (copy, native, QR, embed, print) report to `POST /share/beacon`, which is signed.
- Shares aren't counted for bots or link unfurlers, prefetches, HEAD requests, or the same reader repeating within `tracking.dedupe` minutes.
- Each counted share fires `Atannex\Share\Events\Shared` with the platform, URL, subject (`$event->model()`), IP, user agent, referrer and user ID.

Recorders: `database` (default; one `share_counts` row per address, platform and day, incremented with a single upsert so concurrent shares are all counted), `null`, or your own:

```php
Share::recordUsing(fn (Shared $share) => $share->model()?->increment('shares'));
// or config('share.tracking.recorder') => App\Sharing\MyRecorder::class (implements Recorder)

Share::count($post);                         // or a URL, or a Subject
Share::count(platform: ['x', 'bluesky'], since: now()->subDay());
Share::breakdown($post);
```

## Link previews

```bash
php artisan share:inspect https://example.com/stories/terraces
```

This fetches the page the way a platform's crawler would and reports:

- the title, description and image
- the card each platform will show
- problems: missing tags, a relative or `http` image, an image under 200×200 or not 1.91:1, an image over 5 or 8 MB, an `og:url` that differs from the canonical link, and more

It exits with status 1 on errors, so you can run it in CI. `--json` prints the report as JSON. The same report is available in code with `app(PreviewInspector::class)->inspect($url)`.

## Headless use

For React, Vue, Livewire or your own markup:

```php
$share = Share::for($post);
$share->links()->each(fn (Link $link) => [$link->href, $link->label, $link->icon, $link->attributes()]);
$share->client();   // what the script reads from [data-share]
$share->qr(240);    // inline SVG
```

To keep the package's behaviour in your own markup, put `{!! Link::attributeString($link->attributes()) !!}` on your element, put `data-share='…client() JSON…'` on a container, and include `<x-share::scripts />`.

## Testing

```php
Share::fake();

$this->get($post->share()->href('whatsapp'));

Share::assertShared('whatsapp', $post);
Share::assertShared('whatsapp', times: 1);
Share::assertNotShared('x');
Share::assertNothingShared();
```

The package's own suite: `composer test`.

## Configuration

See [`config/share.php`](config/share.php):

- `platforms`, `presets` and `custom`
- `via`, `site_name`, `hashtags` and `text_limit`
- UTM tags: on by default, existing parameters and the fragment are kept, templates use `{platform}` and `{medium}`
- `tracking` (`enabled`, `recorder`, `dedupe`, `cache`)
- `routes` (`prefix`, `domain`, `middleware`, `throttle`)
- `migrations`, `file_share_max_mb` and `popup`

## Credits

Brand marks come from [Simple Icons](https://simpleicons.org) (CC0). The LinkedIn and Microsoft marks and the interface glyphs come from [Font Awesome Free](https://fontawesome.com) (CC BY 4.0). Trademarks belong to their owners.

## License

MIT
