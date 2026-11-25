# Atannex Share

Share buttons, a share sheet and Open Graph tags for Laravel. Works with models, URLs, plain text and files, across 35+ platforms.

Requires PHP 8.2+ and Laravel 11, 12 or 13.

## Installation

```bash
composer require atannex/share
php artisan migrate
```

The migration creates the `share_counts` table. If you don't want share counting in the database, set `share.migrations` to `false` before migrating.

For QR codes in the share sheet:

```bash
composer require bacon/bacon-qr-code
```

To publish the config file:

```bash
php artisan vendor:publish --tag=share-config
```

Other publish tags: `share-views`, `share-lang`, `share-migrations`.

## Quick start

Add the tags and styles to your layout's `<head>`, then place the buttons and the script where you need them:

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

That's enough for a working set of share buttons with click counting.

## What you can share

`Share::for()` accepts almost anything. The `share()` helper does the same thing.

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

Platforms that need something the share doesn't have are skipped. For example, Pinterest is skipped when there is no image, and Facebook is skipped when there is no link.

### Models

Add the `HasShares` trait and describe the model's fields with `#[ShareAs]`:

```php
use Atannex\Share\Attributes\ShareAs;
use Atannex\Share\Concerns\HasShares;

#[ShareAs(route: 'posts.show', routeKey: 'slug', text: 'excerpt', image: 'cover_url', hashtags: 'tags')]
class Post extends Model
{
    use HasShares;
}
```

Each value can be an attribute, a method or a dot path such as `author.name`. Use `url:` instead of `route:` if the model already has a URL field. Fields you leave out are picked up from common names (`title`, `name`, `description`, `summary`, `image`, `url`, ...).

Without the attribute, those common names are still used, so many models work without any setup.

If you need full control, implement `Shareable`:

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

For classes you can't edit, register a resolver in a service provider:

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

Other options:

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

HTML is stripped, relative URLs become absolute, and long text is cut at a word boundary to fit each platform's limit.

### Getting links

```php
$share = Share::for($post);

$share->links();           // a collection of Link objects
$share->href('whatsapp');  // the button URL (goes through the counter)
$share->to('whatsapp');    // the platform's own URL, not counted
$share->qr(240);           // QR code as inline SVG
```

Returning a share from a controller gives JSON, which is handy for SPAs:

```php
return Share::for($post);
```

## Blade components

Every component takes `:for="$anything"` or `:share="Share::for(...)"`, as well as `only`, `except` and `preset`.

### Buttons

```blade
<x-share::buttons :for="$post" />
<x-share::buttons :for="$post" variant="brand" size="lg" />
<x-share::buttons :for="$post" only="whatsapp,facebook,copy" />
```

- `variant`: `icons`, `pills`, `brand` or `minimal`
- `size`: `sm`, `md` or `lg`
- `vertical`: stack the buttons in a column
- `heading`, `labels`: show a heading and/or text labels
- `sheet="id"`: add a "More" button that opens a share sheet

### Share sheet

A dialog with every platform, a link preview, copy link, QR code, embed code and the device's native share. On phones it opens from the bottom of the screen.

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

Outputs the Open Graph and X card tags, which control how the link looks when posted.

### Scripts and styles

`<x-share::scripts />` inlines the script once per page and picks up Vite's CSP nonce automatically. You can also pass `nonce="..."`.

`<x-share::styles />` is optional. Leave it out to write your own CSS, or keep it and override the variables:

```css
:root {
    --share-size: 2.75rem;
    --share-radius: 0.75rem;
}
```

To send shares to your own analytics:

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

`twitter` works as an alias for `x`, and `mail` for `email`. Messenger, Viber and SMS are hidden on desktop by the default styles.

The default list and presets live in `config/share.php`:

```php
'platforms' => ['facebook', 'x', 'whatsapp', 'linkedin', 'telegram', 'copy', 'native'],

'presets' => [
    'compact' => ['facebook', 'x', 'whatsapp', 'copy'],
],
```

### Adding or changing a platform

In the config:

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

Or in code:

```php
use Atannex\Share\ShareData;

Share::extend('shortener', [
    'label' => 'Short link',
    'build' => fn (ShareData $share) => 'https://s.example.com/new?u='.urlencode($share->url),
]);
```

Available placeholders: `{url}`, `{title}`, `{message}`, `{text}`, `{body}`, `{mail_body}`, `{image}`, `{via}`, `{hashtags}`, `{hashtag_line}`, `{first_hashtag}`, `{tags}`, `{site}`.

To see the URL each platform builds for a page:

```bash
php artisan share:links https://example.com --title="Hello"
```

## Counting shares

Counting is on by default. Button links go through a signed redirect at `/share/{platform}`, and actions like copy, native share and QR report to `/share/beacon`. Bots, link previews, prefetches and repeat clicks within 30 minutes are ignored.

```php
$post->shareCount();                              // total
$post->shareCount('whatsapp', now()->subWeek());  // one platform, last 7 days
$post->shareBreakdown();                          // ['whatsapp' => 41, 'facebook' => 12, ...]

Post::withShareCount()->orderByDesc('shares_count')->get();

Share::count('https://example.com/page');
Share::count(platform: ['x', 'bluesky'], since: now()->subDay());
Share::breakdown($post);
```

Each counted share fires `Atannex\Share\Events\Shared`:

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

Fetches the page as a platform crawler would and reports the title, description and image, plus problems such as missing tags, an image that's too small or too large, or a non-HTTPS image. It exits with code 1 when it finds errors, so you can run it in CI. Add `--json` for JSON output. Requires `guzzlehttp/guzzle`.

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

Options in `config/share.php`:

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

Translations for English and French are included. Publish them with `--tag=share-lang` to change the wording or add a language.

## License

MIT
