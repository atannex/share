<?php

declare(strict_types=1);

namespace Atannex\Share;

use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Events\Shared;
use Atannex\Share\Links\Link;
use Atannex\Share\Links\LinkCollection;
use Atannex\Share\Links\UrlComposer;
use Atannex\Share\Models\ShareCount;
use Atannex\Share\Platforms\Platform;
use Atannex\Share\Platforms\PlatformRegistry;
use Atannex\Share\Recorders\CallbackRecorder;
use Atannex\Share\Resolution\Normalizer;
use Atannex\Share\Resolution\Resolver;
use Atannex\Share\Support\Icons;
use Atannex\Share\Testing\FakeRecorder;
use Atannex\Share\Tracking\Tracker;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * The Share facade's root: turns anything into a share and its links, and
 * counts shares.
 */
final class ShareManager
{
    private ?Recorder $override = null;

    /**
     * @param  array<string, mixed>  $config  config('share')
     * @param  Closure(): Recorder  $recorder  the configured recorder, built when first needed
     */
    public function __construct(
        private readonly PlatformRegistry $platforms,
        private readonly Resolver $resolver,
        private readonly Normalizer $normalizer,
        private readonly UrlComposer $composer,
        private readonly Tracker $tracker,
        private readonly Dispatcher $events,
        private readonly Closure $recorder,
        private readonly array $config = [],
    ) {}

    /**
     * Start a share of anything: a model, a Shareable, an address, some text,
     * an array of fields, a closure, or nothing (the current page).
     */
    public function for(mixed $subject = null): PendingShare
    {
        return new PendingShare($this, $this->resolver->resolve($subject, $this->defaults()));
    }

    /** The current page. */
    public function page(): PendingShare
    {
        return $this->for();
    }

    /**
     * An address with an optional title: Share::url('https://…', 'Title').
     */
    public function url(string $url, ?string $title = null): PendingShare
    {
        return $this->for(array_filter(['url' => $url, 'title' => $title]));
    }

    /**
     * Text with no link (platforms that need one are left out).
     */
    public function text(string $message): PendingShare
    {
        return $this->for(['message' => $message]);
    }

    /**
     * The links for a share, in the order given. Platforms the share lacks
     * something for are left out.
     *
     * @param  list<string>  $keys
     */
    public function links(ShareData $share, array $keys, bool $track = true, bool $utm = true): LinkCollection
    {
        $links = new LinkCollection;
        $composer = $utm ? $this->composer : new UrlComposer;
        $track = $track && $this->tracker->enabled();

        foreach ($keys as $key) {
            $platform = $this->platforms->get($key);

            if (isset($links[$platform->key]) || ! $platform->supports($share) || ($platform->key === 'qr' && ! $this->qrAvailable())) {
                continue;
            }

            $target = $composer->compose($share, $platform);
            $href = $target !== null && $track && $platform->redirects() ? $this->tracker->redirect($platform, $share, $target) : $target;

            $links[$platform->key] = new Link(
                platform: $platform,
                href: $href,
                target: $target,
                value: $this->actionValue($share, $platform, $composer),
                label: __($platform->label),
                icon: Icons::for($platform),
            );
        }

        return $links;
    }

    /**
     * Platform keys for a selection: the given ones, a preset, or the
     * configured default list; minus the excluded ones.
     *
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @return list<string>
     */
    public function select(?array $only = null, array $except = [], ?string $preset = null): array
    {
        if ($preset !== null) {
            $only = $this->config['presets'][$preset] ?? throw new InvalidArgumentException("Unknown share preset [{$preset}].");
        }

        $keys = array_map(fn (string $key) => $this->platforms->get($key)->key, $only ?? (array) ($this->config['platforms'] ?? []));
        $except = array_map(fn (string $key) => $this->platforms->key($key) ?? $key, $except);

        return array_values(array_unique(array_diff($keys, $except)));
    }

    /**
     * Count a share and fire Shared. False when it wasn't counted (a bot, a
     * prefetch, or a repeat within the dedupe window).
     */
    public function record(string $platform, ?string $url = null, Subject|Model|null $subject = null, ?Request $request = null): bool
    {
        $platform = $this->platforms->get($platform)->key;
        $subject = $subject instanceof Model ? Subject::of($subject) : $subject;

        if ($request !== null && (! $this->tracker->countable($request) || ! $this->tracker->firstTime($request, $platform, $url, $subject))) {
            return false;
        }

        $event = new Shared(
            platform: $platform,
            url: $url,
            subject: $subject,
            ip: $request?->ip(),
            userAgent: $request?->userAgent(),
            referrer: $request?->headers->get('referer'),
            userId: $request?->user()?->getAuthIdentifier(),
        );

        $this->recorder()->record($event);
        $this->events->dispatch($event);

        return true;
    }

    /**
     * Shares counted by the database recorder: of a model, a Subject or an
     * address; on some platforms; since a date.
     */
    public function count(Model|Subject|string|null $subject = null, string|array|null $platform = null, ?DateTimeInterface $since = null): int
    {
        return (int) ShareCount::query()
            ->when($subject !== null, fn ($query) => $query->about($subject))
            ->when($platform !== null, fn ($query) => $query->on(...array_map(fn ($key) => $this->platforms->key($key) ?? $key, (array) $platform)))
            ->when($since !== null, fn ($query) => $query->between($since))
            ->sum('total');
    }

    /**
     * platform => shares, most shared first.
     *
     * @return array<string, int>
     */
    public function breakdown(Model|Subject|string|null $subject = null, ?DateTimeInterface $since = null): array
    {
        return ShareCount::query()
            ->when($subject !== null, fn ($query) => $query->about($subject))
            ->when($since !== null, fn ($query) => $query->between($since))
            ->selectRaw('platform, sum(total) as shares')
            ->groupBy('platform')
            ->orderByDesc('shares')
            ->orderBy('platform')
            ->pluck('shares', 'platform')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Add a platform, or change a built-in one:
     * Share::extend('intranet', ['label' => 'Intranet', 'url' => 'https://intra/share', 'query' => ['u' => '{url}']]).
     * 'build' => fn (ShareData $share, Platform $platform) => '…' builds the address in code.
     *
     * @param  array<string, mixed>|Platform  $definition
     */
    public function extend(string $key, array|Platform $definition): self
    {
        $this->platforms->define($key, $definition);

        return $this;
    }

    /**
     * How instances of a class (or its subclasses) are shared, for classes you can't change.
     *
     * @param  class-string  $class
     * @param  Closure(object): (ShareData|array<string, mixed>)  $resolver
     */
    public function resolveUsing(string $class, Closure $resolver): self
    {
        $this->resolver->register($class, $resolver);

        return $this;
    }

    /**
     * Count shares your own way (replaces the configured recorder).
     *
     * @param  Recorder|Closure(Shared): mixed  $recorder
     */
    public function recordUsing(Recorder|Closure $recorder): self
    {
        $this->override = $recorder instanceof Closure ? new CallbackRecorder($recorder) : $recorder;

        return $this;
    }

    public function recorder(): Recorder
    {
        return $this->override ??= ($this->recorder)();
    }

    /**
     * Swap the recorder for one that keeps shares in memory, for assertions.
     */
    public function fake(): FakeRecorder
    {
        $fake = new FakeRecorder;
        $this->override = $fake;

        return $fake;
    }

    public function assertShared(string $platform, Model|Subject|string|null $subject = null, ?int $times = null): void
    {
        $this->faked()->assertShared($this->platforms->key($platform) ?? $platform, $subject, $times);
    }

    public function assertNotShared(string $platform, Model|Subject|string|null $subject = null): void
    {
        $this->faked()->assertNotShared($this->platforms->key($platform) ?? $platform, $subject);
    }

    public function assertNothingShared(): void
    {
        $this->faked()->assertNothingShared();
    }

    public function platforms(): PlatformRegistry
    {
        return $this->platforms;
    }

    public function platform(string $key): Platform
    {
        return $this->platforms->get($key);
    }

    public function tracker(): Tracker
    {
        return $this->tracker;
    }

    public function composer(): UrlComposer
    {
        return $this->composer;
    }

    public function normalizer(): Normalizer
    {
        return $this->normalizer;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }

    public function qrAvailable(): bool
    {
        return class_exists(Writer::class);
    }

    /**
     * An SVG QR code (needs bacon/bacon-qr-code), or null without it.
     */
    public function qr(string $text, int $size = 220): ?string
    {
        if (! $this->qrAvailable()) {
            return null;
        }

        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);

        // Drop the XML prolog so it can sit inline in HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg));
    }

    /**
     * Values every share starts with (config: via, site_name; the app locale).
     */
    public function defaults(): ShareData
    {
        return new ShareData(
            via: ($via = ltrim((string) ($this->config['via'] ?? ''), '@')) === '' ? null : $via,
            siteName: ($site = (string) ($this->config['site_name'] ?? '')) === '' ? null : $site,
            locale: str_replace('-', '_', (string) app()->getLocale()) ?: null,
        );
    }

    private function actionValue(ShareData $share, Platform $platform, UrlComposer $composer): ?string
    {
        $url = $share->url === null ? null : $composer->tag($share->url, $platform);

        return match ($platform->key) {
            'copy' => $url ?? $share->message(),
            'native', 'qr' => $url,
            'embed' => $share->embed,
            default => null,
        };
    }

    private function faked(): FakeRecorder
    {
        if (! $this->override instanceof FakeRecorder) {
            throw new RuntimeException('Call Share::fake() before asserting on shares.');
        }

        return $this->override;
    }
}
