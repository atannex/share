<?php

declare(strict_types=1);

namespace Atannex\Share;

use Atannex\Share\Links\Link;
use Atannex\Share\Links\LinkCollection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use JsonSerializable;

/**
 * A share being put together:
 *
 *     Share::for($post)->only('x', 'whatsapp')->hashtags('Lebialem')->links();
 *     Share::url('https://…', 'Title')->to('linkedin');
 *     return Share::for($post);   // JSON for an SPA: the share and its links
 *
 * @implements Arrayable<string, mixed>
 */
final class PendingShare implements Arrayable, Htmlable, JsonSerializable, Responsable
{
    /** @var list<string>|null */
    private ?array $only = null;

    /** @var list<string> */
    private array $except = [];

    private ?string $preset = null;

    private bool $track = true;

    private bool $utm = true;

    private ?LinkCollection $links = null;

    public function __construct(
        private readonly ShareManager $manager,
        private ShareData $data,
    ) {}

    /* What is shared ------------------------------------------------------ */

    public function url(?string $url): self
    {
        return $this->set(['url' => $url]);
    }

    public function title(?string $title): self
    {
        return $this->set(['title' => $title]);
    }

    /**
     * The post text (defaults to the title). Shortened to each platform's limit.
     */
    public function message(?string $message): self
    {
        return $this->set(['message' => $message]);
    }

    /**
     * The description: email bodies, Tumblr captions, the preview card, meta tags.
     */
    public function text(?string $text): self
    {
        return $this->set(['text' => $text]);
    }

    public function image(mixed $image, ?string $alt = null, ?int $width = null, ?int $height = null): self
    {
        return $this->set(array_filter(['image' => $image, 'image_alt' => $alt, 'image_width' => $width, 'image_height' => $height], fn ($value) => $value !== null) + ['image' => null]);
    }

    /**
     * @param  string|iterable<mixed>  ...$hashtags  "Lebialem", ['land use'], a collection of tag models
     */
    public function hashtags(mixed ...$hashtags): self
    {
        return $this->set(['hashtags' => $hashtags]);
    }

    /** The account credited ("via @handle"). */
    public function via(?string $handle): self
    {
        return $this->set(['via' => $handle]);
    }

    /**
     * A video for meta tags (og:video, X's player card).
     *
     * @param  string|null  $player  an embeddable player page
     */
    public function video(mixed $url, ?string $mime = null, ?int $width = null, ?int $height = null, ?string $player = null): self
    {
        return $this->set(['video' => $url === null ? null : ['url' => $url, 'mime' => $mime, 'width' => $width, 'height' => $height, 'player' => $player]]);
    }

    /**
     * A file the device's share sheet can post itself (a video to Instagram or
     * TikTok, a PDF to a chat), where the device supports it.
     */
    public function file(mixed $url, ?string $mime = null, ?string $name = null, ?int $size = null): self
    {
        $file = $this->manager->normalizer()->value('files', [array_filter(['url' => $url, 'mime' => $mime, 'name' => $name, 'size' => $size], fn ($value) => $value !== null)]);

        return $this->set(['files' => [...$this->data->files, ...$file]]);
    }

    /** HTML to copy for embedding (offered by the "embed" platform). */
    public function embed(?string $html): self
    {
        return $this->set(['embed' => $html]);
    }

    /** og:type: article, website, video.other, profile… */
    public function type(?string $type): self
    {
        return $this->set(['type' => $type]);
    }

    public function siteName(?string $name): self
    {
        return $this->set(['site_name' => $name]);
    }

    /**
     * Extra meta tags for <x-share::meta> (property => content).
     *
     * @param  array<string, string>  $tags
     */
    public function meta(array $tags): self
    {
        return $this->set(['meta' => [...$this->data->meta, ...$tags]]);
    }

    /** The model shares are counted against. */
    public function about(Model|Subject|null $subject): self
    {
        return $this->set(['subject' => $subject]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function with(array $values): self
    {
        return $this->set($values);
    }

    /* Where to ----------------------------------------------------------------- */

    /**
     * @param  string|list<string>  ...$platforms  in the order to show them
     */
    public function only(string|array ...$platforms): self
    {
        $this->only = self::keys($platforms);
        $this->preset = null;
        $this->links = null;

        return $this;
    }

    public function except(string|array ...$platforms): self
    {
        $this->except = [...$this->except, ...self::keys($platforms)];
        $this->links = null;

        return $this;
    }

    /** A named set from config('share.presets'). */
    public function preset(?string $name): self
    {
        $this->preset = $name;
        $this->links = null;

        return $this;
    }

    /** Link straight to the platforms; shares are not counted. */
    public function withoutTracking(): self
    {
        $this->track = false;
        $this->links = null;

        return $this;
    }

    /** No campaign tags on the shared address. */
    public function withoutUtm(): self
    {
        $this->utm = false;
        $this->links = null;

        return $this;
    }

    /* Results -------------------------------------------------------------------- */

    public function data(): ShareData
    {
        return $this->data;
    }

    /**
     * The platform keys this share will offer, in order.
     *
     * @return list<string>
     */
    public function platformKeys(): array
    {
        return $this->manager->select($this->only, $this->except, $this->preset);
    }

    public function links(): LinkCollection
    {
        return $this->links ??= $this->manager->links($this->data, $this->platformKeys(), $this->track, $this->utm);
    }

    /**
     * One platform's link, whether or not it is in the selection. Null when the
     * share lacks what it needs (Pinterest without an image).
     */
    public function link(string $platform): ?Link
    {
        $key = $this->manager->platform($platform)->key;

        return $this->links()->get($key) ?? $this->manager->links($this->data, [$key], $this->track, $this->utm)->get($key);
    }

    /** The platform's own share address (not counted). */
    public function to(string $platform): ?string
    {
        return $this->link($platform)?->target;
    }

    /** What a button for the platform opens (counted when tracking is on). */
    public function href(string $platform): ?string
    {
        return $this->link($platform)?->href;
    }

    /**
     * The settings the page script reads from [data-share].
     *
     * @return array<string, mixed>
     */
    public function client(): array
    {
        $data = $this->data;
        $tracker = $this->manager->tracker();
        $maxBytes = (int) $this->manager->config('file_share_max_mb', 60) * 1024 * 1024;

        return array_filter([
            'beacon' => $this->track && $tracker->enabled() ? $tracker->beacon($data) : null,
            'title' => $data->title,
            'text' => $data->message(),
            'url' => $data->url === null ? null : ($this->utm ? $this->manager->composer()->tag($data->url, 'native', 'share') : $data->url),
            'files' => array_values(array_filter($data->files, fn ($file) => $file['size'] === null || $file['size'] <= $maxBytes)),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /** An SVG QR code of the address (needs bacon/bacon-qr-code). */
    public function qr(int $size = 220): ?string
    {
        $value = $this->link('qr')?->value;

        return $value === null ? null : $this->manager->qr($value, $size);
    }

    /** Open Graph and X card tags for the page's <head>. */
    public function metaTags(): HtmlString
    {
        return new HtmlString(view('share::meta', ['share' => $this->data])->render());
    }

    /**
     * The default buttons (<x-share::buttons>).
     */
    public function toHtml(): string
    {
        return view('share::buttons', [
            'share' => $this,
            'links' => $this->links(),
            'variant' => 'icons',
            'size' => 'md',
            'labels' => false,
            'sheet' => null,
            'heading' => null,
            'vertical' => false,
            'attributes' => new ComponentAttributeBag,
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'share' => $this->data->toArray(),
            'links' => $this->links()->map->toArray()->all(),
            'client' => $this->client(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray());
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function set(array $values): self
    {
        $this->data = $this->manager->normalizer()->apply($this->data, $values);
        $this->links = null;

        return $this;
    }

    /**
     * @param  array<int, string|array<int, string>>  $platforms
     * @return list<string>
     */
    private static function keys(array $platforms): array
    {
        $keys = [];

        foreach ($platforms as $platform) {
            foreach ((array) $platform as $key) {
                foreach (explode(',', (string) $key) as $part) {
                    if (($part = trim($part)) !== '') {
                        $keys[] = $part;
                    }
                }
            }
        }

        return $keys;
    }
}
