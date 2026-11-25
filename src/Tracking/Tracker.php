<?php

declare(strict_types=1);

namespace Atannex\Share\Tracking;

use Atannex\Share\Platforms\Platform;
use Atannex\Share\ShareData;
use Atannex\Share\Subject;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;

/**
 * Signed share links and the rules for what counts as a share.
 *
 * Links are signed relative to the host (they keep working behind proxies
 * and on every domain the site answers on), so nobody can use the redirect
 * to send readers elsewhere or count shares of addresses that were never
 * offered.
 */
final class Tracker
{
    /** Link-preview fetchers, crawlers and scripts. */
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|facebot|embedly|preview|whatsapp\/|telegrambot|discord|slack|linkedinbot|skypeuri|bingpreview|headless|lighthouse|pingdom|monitor|curl|wget|python|go-http|java\/|okhttp|axios|node-fetch|httpclient|guzzle/i';

    public function __construct(
        private readonly UrlGenerator $urls,
        private readonly bool $enabled,
        private readonly ?Cache $cache = null,
        private readonly int $dedupe = 30,
        private readonly ?string $domain = null,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * The counted address for a web platform: GET /share/{platform}?to=…&signature=…
     */
    public function redirect(Platform $platform, ShareData $share, string $target): string
    {
        return $this->absolute($this->urls->signedRoute('share.redirect', array_filter([
            'platform' => $platform->key,
            'to' => $target,
            'u' => $share->url,
            'ref' => $share->subject?->reference(),
        ]), absolute: false));
    }

    /**
     * Where the page script reports shares done in the browser.
     */
    public function beacon(ShareData $share): string
    {
        return $this->absolute($this->urls->signedRoute('share.beacon', array_filter([
            'u' => $share->url,
            'ref' => $share->subject?->reference(),
        ]), absolute: false));
    }

    /**
     * A relative signed path made absolute, on the routes' own domain when one is set
     * (a relative signed route loses it).
     */
    private function absolute(string $path): string
    {
        if ($this->domain === null || $this->domain === '') {
            return $this->urls->to($path);
        }

        $scheme = parse_url($this->urls->to('/'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$this->domain.$path;
    }

    /**
     * Not a prefetch, a HEAD request, or a bot unfurling the link.
     */
    public function countable(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return false;
        }

        foreach (['Purpose', 'Sec-Purpose', 'X-Purpose', 'X-Moz'] as $header) {
            if (str_contains(strtolower((string) $request->headers->get($header)), 'prefetch')) {
                return false;
            }
        }

        $agent = (string) $request->userAgent();

        return $agent !== '' && ! preg_match(self::BOTS, $agent);
    }

    /**
     * False when this reader shared the same thing on the same platform
     * within the dedupe window.
     */
    public function firstTime(Request $request, string $platform, ?string $url, ?Subject $subject): bool
    {
        if ($this->dedupe <= 0 || $this->cache === null) {
            return true;
        }

        return $this->cache->add($this->seenKey($request, $platform, $url, $subject), 1, $this->dedupe * 60);
    }

    /**
     * Undo firstTime() for a share that failed to record, so a retry counts.
     */
    public function forget(Request $request, string $platform, ?string $url, ?Subject $subject): void
    {
        $this->cache?->forget($this->seenKey($request, $platform, $url, $subject));
    }

    private function seenKey(Request $request, string $platform, ?string $url, ?Subject $subject): string
    {
        return 'share:seen:'.sha1(implode('|', [$request->ip(), $request->userAgent(), $platform, $url, $subject?->reference()]));
    }
}
