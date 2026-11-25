# Atannex Share

Share buttons, a share sheet and Open Graph metadata for Laravel. Share models, URLs, text or files with more than 35 platforms.

Requires PHP 8.2+ and Laravel 11, 12 or 13.

## Installation

```bash
composer require atannex/share

php artisan migrate
```

This creates the `share_counts` table used for share tracking. If you don’t need database tracking, set `share.migrations` to `false` before running the migration.

QR code support in the share sheet is optional. Install it with:

```bash
composer require bacon/bacon-qr-code
```

Publish the configuration file if you want to customize the package:

```bash
php artisan vendor:publish --tag=share-config
```

You can also publish the views, translations or migrations with the `share-views`, `share-lang` and `share-migrations` tags.

## Quick start

Add the meta tags and styles to your layout’s `<head>`. Put the buttons and script wherever you want them to appear:

```blade
<head>

    <x-share::meta :for="$post" />

    <x-share::styles />

</head>

<body>

    <x-share::buttons :for="$post" />

    <x-share::scripts />

</body>
```

This gives you share buttons with tracking enabled.

## What you can share

Use `Share::for()` to build a share from a model, URL, text, array or closure. The `share()` helper is also available.

```php
use Atannex\Share\Facades\Share;

Share::for($post);                          // an Eloquent model

Share::for('https://example.com/page');     // a URL

Share::for('/about');                       // a relative path (made absolute)

Share::for('Some text to share');           // text only

Share::for(['url' => $url, 'title' => $t]); // an array

Share::for(fn () => [...]);                 // a closure returning any of the above

Share::for();                               // the current page

Share::url('https://example.com', 'Title');

Share::text('Text with no link');
```

A platform is omitted when the share data it needs is missing. For example, Pinterest needs an image and Facebook needs a URL.

### Models

For Eloquent models, add the `HasShares` trait. Use `#[ShareAs]` to tell the package which fields to use:

```php
use Atannex\Share\Attributes\ShareAs;

use Atannex\Share\Concerns\HasShares;

#[ShareAs(route: 'posts.show', routeKey: 'slug', text: 'excerpt', image: 'cover_url', hashtags: 'tags')]

class Post extends Model

{

    use HasShares;

}
```

Each `ShareAs` value can point to an attribute, method or dot path (for example, `author.name`). If the model already has a URL field, use `url:` instead of `route:`. Any fields you omit are inferred from common names such as `title`, `name`, `description`, `summary`, `image` and `url.

You can also leave off the attribute entirely. The package will still look for those common fields, which is enough for many models.

For custom share data, implement the `Shareable` contract:

```php
use Atannex\Share\Contracts\Shareable;

class Fixture extends Model implements Shareable

{

    public function toShare(): array

    {

        return [

            'url' => route('fixtures.show', $this),

            'title' => "{$this->home} {$this->score} {$this->away}",

            'message' => "Full time: {$this->home} {$this->score} {$this->away}",

            'image' => $this->card_image_url,

            'hashtags' => [$this->league->name],

        ];

    }

}
```

If the class belongs to another package or you’d rather not modify it, register a resolver in a service provider:

```php
Share::resolveUsing(Invoice::class, fn (Invoice $invoice) => [

    'url' => route('invoices.public', $invoice),

    'title' => "Invoice {$invoice->number}",

]);
```

### Adjusting a share

```php
Share::for($post)

    ->title('The new bridge is open')

    ->message('The bridge is finally open!')     // the post text; defaults to the title

    ->text('A longer description for email and link cards')

    ->image($post->cover_url, alt: 'The bridge', width: 1200, height: 630)

    ->hashtags('Bamenda', $post->tags)

    ->via('yourhandle')                          // "via @yourhandle" on X

    ->only('facebook', 'x', 'whatsapp', 'copy');
```

You can also use these methods:

| Method                             | Use                                                                  |

| ---------------------------------- | -------------------------------------------------------------------- |

| `->except('reddit')`               | Remove platforms from the list                                       |

| `->preset('compact')`              | Use a named set of platforms from the config                         |

| `->video($url, player: $embedUrl)` | Add `og:video` and an X player card                                  |

| `->file($url, 'video/mp4')`        | Let the device share the file itself (Instagram, TikTok, AirDrop...) |

| `->embed('<iframe ...></iframe>')` | Code shown in the sheet's embed section                              |

| `->meta([...])`                    | Extra Open Graph tags                                                |

| `->about($model)`                  | Count shares against a model when the subject isn't one              |

| `->withoutTracking()`              | Link straight to the platform, without counting                      |

| `->withoutUtm()`                   | Don't add UTM parameters to the shared URL                           |

HTML is removed, relative URLs are converted to absolute URLs, and text is trimmed at a word boundary to fit platform limits.

### Getting links

```php
$share = Share::for($post);

$share->links();           // a collection of Link objects

$share->href('whatsapp');  // the button URL (goes through the counter)

$share->to('whatsapp');    // the platform's own URL, not counted

$share->qr(240);           // QR code as inline SVG
```

You can return the share directly from a controller. The response is JSON, which can be useful for an SPA:

```php
return Share::for($post);
```

## Blade components

All components accept either `:for="$anything"` or `:share="Share::for(...)"`. You can also pass `only`, `except` or `preset` to control which platforms are shown.

### Buttons

```blade
<x-share::buttons :for="$post" />

<x-share::buttons :for="$post" variant="brand" size="lg" />

<x-share::buttons :for="$post" only="whatsapp,facebook,copy" />
```

\- `variant`: `icons`, `pills`, `brand` or `minimal`

\- `size`: `sm`, `md` or `lg`

\- `vertical`: stack the buttons in a column

\- `heading`, `labels`: show a heading and/or text labels

\- `sheet="id"`: add a "More" button that opens a share sheet

### Share sheet

The share sheet includes the platform list, link preview, copy link, QR code, embed code and native sharing. On mobile, it opens as a bottom sheet.

```blade
<x-share::buttons :for="$post" preset="compact" sheet="share-sheet" />

<x-share::sheet :for="$post" id="share-sheet" />
```

Any element can open it:

```blade
<button data-share-open="share-sheet">Share</button>
```

### Meta tags

```blade
<x-share::meta :for="$post" />
```

This outputs Open Graph and X card metadata used to display link previews when someone shares the page.

### Scripts and styles

`<x-share::scripts />` includes the script inline once per page and automatically uses Vite’s CSP nonce. You can pass a `nonce` yourself if needed.

The styles component is optional. Use your own CSS if you prefer, or override these variables:

```css
:root {

    \--share-size: 2.75rem;

    \--share-radius: 0.75rem;

}
```

You can listen for the share event and send it to your analytics service:

```js
document.addEventListener('share:shared', (e) => {

    gtag('event', 'share', { method: e.detail.platform });

});
```

## Platforms

```bash
php artisan share:platforms
```

| Type           | Platforms                                                                                                                                                                              |

| -------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |

| Social         | facebook, x, threads, bluesky, mastodon, fediverse, linkedin, reddit, pinterest, tumblr, hackernews, flipboard, buffer, xing, vk, odnoklassniki, weibo, qzone, naver, hatena, snapchat |

| Messaging      | whatsapp, telegram, line, messenger, viber, sms                                                                                                                                        |

| Work and email | teams, classroom, trello, gmail, outlook, email                                                                                                                                        |

| Browser        | copy, native, qr, embed, print                                                                                                                                                         |

The `twitter` alias maps to `x`, and `mail` maps to `email`. Messenger, Viber and SMS are hidden on desktop by default.

Set the default platforms and presets in `config/share.php`:

```php
'platforms' => ['facebook', 'x', 'whatsapp', 'linkedin', 'telegram', 'copy', 'native'],

'presets' => [

    'compact' => ['facebook', 'x', 'whatsapp', 'copy'],

],
```

### Adding or changing a platform

Add custom platforms or override built-in ones in the config:

```php
'custom' => [

    'mastodon' => ['instance' => 'mas.to'],

    'intranet' => [

        'label' => 'Intranet',

        'url' => 'https://intra.example.com/share',

        'query' => ['u' => '{url}', 't' => '{title}'],

        'color' => '#0f172a',

    ],

],
```

You can also register a platform in code:

```php
use Atannex\Share\ShareData;

Share::extend('shortener', [

    'label' => 'Short link',

    'build' => fn (ShareData $share) => 'https://s.example.com/new?u='.urlencode($share->url),

]);
```

Custom platform URLs can use these placeholders: `{url}`, `{title}`, `{message}`, `{text}`, `{body}`, `{mail_body}`, `{image}`, `{via}`, `{hashtags}`, `{hashtag_line}`, `{first_hashtag}`, `{tags}`, `{site}`.

Use this command to inspect the URL generated for each platform:

```bash
php artisan share:links https://example.com --title="Hello"
```

## Counting shares

Share counting is enabled by default. Button clicks use a signed redirect at `/share/{platform}`. Actions such as copy, native share and QR code use `/share/beacon`. The package ignores bots, link previews, prefetches and repeat clicks within a 30-minute window.

```php
$post->shareCount();                              // total

$post->shareCount('whatsapp', now()->subWeek());  // one platform, last 7 days

$post->shareBreakdown();                          // ['whatsapp' => 41, 'facebook' => 12, ...]

Post::withShareCount()->orderByDesc('shares_count')->get();

Share::count('https://example.com/page');

Share::count(platform: ['x', 'bluesky'], since: now()->subDay());

Share::breakdown($post);
```

A successful share fires the `Atannex\Share\Events\Shared` event:

```php
use Atannex\Share\Events\Shared;

Event::listen(function (Shared $event) {

    // $event->model(), $event->platform, ...

});
```

To store counts somewhere else, pass a closure or set `tracking.recorder` to a class implementing `Atannex\Share\Contracts\Recorder`:

```php
Share::recordUsing(fn (Shared $share) => $share->model()?->increment('shares'));
```

Set `SHARE_TRACKING=false` in `.env` to turn counting off.

## Checking link previews

```bash
php artisan share:inspect https://example.com/blog/my-post
```

The command fetches the page using a crawler-style request and checks its title, description and image metadata. It also reports issues such as missing tags, images that are too small or too large, and images served over non-HTTPS. It exits with code `1` if errors are found, making it suitable for CI. Pass `--json` for JSON output. This command requires `guzzlehttp/guzzle`.

## Testing

```php
use Atannex\Share\Facades\Share;

Share::fake();

$this->get($post->share()->href('whatsapp'));

Share::assertShared('whatsapp', $post);

Share::assertShared('whatsapp', times: 1);

Share::assertNotShared('x');

Share::assertNothingShared();
```

## Configuration

The main options in `config/share.php` are:

| Key                 | Purpose                                                             |

| ------------------- | ------------------------------------------------------------------- |

| `platforms`         | Default platforms and their order                                   |

| `presets`           | Named sets of platforms                                             |

| `custom`            | Your own platforms, or changes to built-in ones                     |

| `via`               | Your X handle (`SHARE_VIA`)                                         |

| `site_name`         | Site name for meta tags (`SHARE_SITE_NAME`, defaults to `APP_NAME`) |

| `hashtags`          | Maximum number of hashtags per post                                 |

| `text_limit`        | Maximum description length                                          |

| `utm`               | UTM parameters added to shared URLs (`SHARE_UTM`)                   |

| `tracking`          | Counting, the recorder and the repeat-click window                  |

| `routes`            | Prefix, domain, middleware and throttle for the share routes        |

| `migrations`        | Whether to run the package migration                                |

| `file_share_max_mb` | Largest file the script will download for native file sharing       |

| `popup`             | Desktop popup width and height                                      |

English and French translations are included. Publish them using `--tag=share-lang` to customize the text or add another language.

## License

MIT
