<?php

declare(strict_types=1);

namespace Atannex\Share\Inspection;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Reads a page the way platforms do when a link is pasted, and says what
 * the preview card will look like and what is missing or wrong.
 */
final class PreviewInspector
{
    private const AGENT = 'Mozilla/5.0 (compatible; atannex-share-inspector/1.0)';

    public function __construct(private readonly Http $http) {}

    /**
     * @return array{
     *     url: string, status: ?int, tags: array<string, string>, title: ?string, description: ?string,
     *     image: ?array{url: string, status: ?int, type: ?string, width: ?int, height: ?int, bytes: ?int},
     *     card: string, issues: list<array{level: string, message: string}>
     * }
     */
    public function inspect(string $url, bool $checkImage = true): array
    {
        $issues = [];
        $report = ['url' => $url, 'status' => null, 'tags' => [], 'title' => null, 'description' => null, 'image' => null, 'card' => 'none', 'issues' => &$issues];

        try {
            $response = $this->http->withHeaders(['User-Agent' => self::AGENT, 'Accept' => 'text/html'])->timeout(15)->get($url);
        } catch (Throwable $e) {
            $issues[] = ['level' => 'error', 'message' => 'The page could not be fetched: '.$e->getMessage()];

            return $this->finish($report);
        }

        $report['status'] = $response->status();
        if (! $response->successful()) {
            $issues[] = ['level' => 'error', 'message' => "The page answered HTTP {$response->status()}; platforms show no preview."];

            return $this->finish($report);
        }

        [$tags, $title, $canonical] = $this->parse((string) $response->body());
        $report['tags'] = $tags;

        $og = fn (string $key) => $tags['og:'.$key] ?? null;
        $tw = fn (string $key) => $tags['twitter:'.$key] ?? null;

        $report['title'] = $og('title') ?? $tw('title') ?? $title;
        $report['description'] = $og('description') ?? $tw('description') ?? ($tags['description'] ?? null);
        $imageUrl = $og('image') ?? $og('image:url') ?? $tw('image');

        if ($og('title') === null) {
            $issues[] = ['level' => $title ? 'warning' : 'error', 'message' => 'No og:title'.($title ? '; platforms fall back to <title>.' : ' and no <title>.')];
        } elseif (mb_strlen($og('title')) > 90) {
            $issues[] = ['level' => 'warning', 'message' => 'og:title is '.mb_strlen($og('title')).' characters; most cards cut it after about 60–90.'];
        }

        if ($report['description'] === null) {
            $issues[] = ['level' => 'warning', 'message' => 'No og:description; the card shows no summary.'];
        } elseif (mb_strlen($report['description']) > 300) {
            $issues[] = ['level' => 'notice', 'message' => 'The description is long ('.mb_strlen($report['description']).' characters); cards show about 200.'];
        }

        if ($og('url') === null) {
            $issues[] = ['level' => 'notice', 'message' => 'No og:url; shares of variants of this address (tracking parameters…) are counted apart.'];
        } elseif ($canonical && rtrim($canonical, '/') !== rtrim($og('url'), '/')) {
            $issues[] = ['level' => 'warning', 'message' => "og:url ({$og('url')}) differs from the canonical link ({$canonical})."];
        }

        if ($og('type') === null) {
            $issues[] = ['level' => 'notice', 'message' => 'No og:type; "website" is assumed.'];
        }

        if ($imageUrl === null) {
            $issues[] = ['level' => 'warning', 'message' => 'No og:image; the card has no picture (and Pinterest can\'t be offered).'];
        } else {
            if (! preg_match('~^https?://~i', $imageUrl)) {
                $issues[] = ['level' => 'error', 'message' => "og:image is not an absolute address ({$imageUrl}); platforms ignore it."];
            } elseif (str_starts_with($url, 'https://') && str_starts_with($imageUrl, 'http://')) {
                $issues[] = ['level' => 'warning', 'message' => 'og:image is served over http on an https page; some platforms drop it.'];
            }

            $report['image'] = $checkImage && preg_match('~^https?://~i', $imageUrl) ? $this->image($imageUrl, $issues) : ['url' => $imageUrl, 'status' => null, 'type' => null, 'width' => null, 'height' => null, 'bytes' => null];
        }

        if ($tw('card') === null) {
            $issues[] = ['level' => 'notice', 'message' => 'No twitter:card; X shows a small "summary" card.'];
        }

        $report['card'] = $imageUrl === null ? 'text' : (($tw('card') ?? 'summary') === 'summary' ? 'small image' : 'large image');

        return $this->finish($report);
    }

    /**
     * @param  list<array{level: string, message: string}>  $issues
     * @return array{url: string, status: ?int, type: ?string, width: ?int, height: ?int, bytes: ?int}
     */
    private function image(string $url, array &$issues): array
    {
        $image = ['url' => $url, 'status' => null, 'type' => null, 'width' => null, 'height' => null, 'bytes' => null];

        try {
            $response = $this->http->withHeaders(['User-Agent' => self::AGENT])->timeout(15)->get($url);
        } catch (Throwable $e) {
            $issues[] = ['level' => 'error', 'message' => 'og:image could not be fetched: '.$e->getMessage()];

            return $image;
        }

        $image['status'] = $response->status();
        if (! $response->successful()) {
            $issues[] = ['level' => 'error', 'message' => "og:image answered HTTP {$response->status()}."];

            return $image;
        }

        $body = (string) $response->body();
        $image['bytes'] = strlen($body);
        $image['type'] = strtok((string) $response->header('Content-Type'), ';') ?: null;

        $size = @getimagesizefromstring($body);
        if ($size !== false) {
            [$image['width'], $image['height']] = [$size[0], $size[1]];
            $image['type'] ??= $size['mime'];
        }

        if ($image['type'] && ! in_array($image['type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $issues[] = ['level' => 'warning', 'message' => "og:image is {$image['type']}; JPEG, PNG, WebP and GIF work everywhere."];
        }

        if ($image['bytes'] > 8 * 1024 * 1024) {
            $issues[] = ['level' => 'error', 'message' => 'og:image is over 8 MB; Facebook and X ignore it.'];
        } elseif ($image['bytes'] > 5 * 1024 * 1024) {
            $issues[] = ['level' => 'warning', 'message' => 'og:image is over 5 MB; X ignores it.'];
        }

        if ($image['width'] !== null && $image['height'] !== null) {
            if ($image['width'] < 200 || $image['height'] < 200) {
                $issues[] = ['level' => 'error', 'message' => "og:image is {$image['width']}×{$image['height']}; Facebook needs at least 200×200."];
            } elseif ($image['width'] < 1200 || $image['height'] < 630) {
                $issues[] = ['level' => 'notice', 'message' => "og:image is {$image['width']}×{$image['height']}; 1200×630 makes a sharp large card."];
            }

            $ratio = $image['width'] / max(1, $image['height']);
            if ($ratio < 1.5 || $ratio > 2.2) {
                $issues[] = ['level' => 'notice', 'message' => sprintf('og:image is %.2f:1; large cards are 1.91:1 and crop the rest.', $ratio)];
            }
        }

        return $image;
    }

    /**
     * @return array{0: array<string, string>, 1: ?string, 2: ?string}
     */
    private function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $tags = [];

        foreach ($xpath->query('//meta[@property or @name]') ?: [] as $meta) {
            /** @var \DOMElement $meta */
            $key = strtolower(trim($meta->getAttribute('property') ?: $meta->getAttribute('name')));
            $content = trim($meta->getAttribute('content'));

            // The first value wins, as on Facebook.
            if ($key !== '' && $content !== '' && ! isset($tags[$key])) {
                $tags[$key] = $content;
            }
        }

        $title = trim((string) $xpath->evaluate('string(//title)')) ?: null;
        $canonical = trim((string) $xpath->evaluate('string(//link[@rel="canonical"]/@href)')) ?: null;

        return [$tags, $title, $canonical];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function finish(array $report): array
    {
        $issues = $report['issues'];
        unset($report['issues']);
        $report['issues'] = $issues;

        return $report;
    }
}
