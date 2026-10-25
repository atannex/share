<?php

declare(strict_types=1);

namespace Atannex\Share\Links;

use Atannex\Share\Platforms\Platform;
use Atannex\Share\ShareData;
use Atannex\Share\Support\Text;

/**
 * Builds a platform's share address from its template. Every value is
 * percent-encoded (RFC 3986), so titles with "&", "#" or quotes and links
 * with query strings arrive intact; a parameter that renders empty is left
 * out; the post text is shortened to the platform's limit.
 */
final class UrlComposer
{
    /**
     * @param  array<string, string>  $utm  parameter => template ({platform}, {medium}); empty for none
     */
    public function __construct(private readonly array $utm = []) {}

    /**
     * The address readers open, or null for a platform done in the browser.
     */
    public function compose(ShareData $share, Platform $platform): ?string
    {
        if ($platform->isClient()) {
            return null;
        }

        $tagged = $share->url === null ? $share : $share->with(['url' => $this->tag($share->url, $platform)]);

        if ($platform->builder !== null) {
            return ($platform->builder)($tagged, $platform);
        }

        $values = $this->placeholders($tagged, $platform);
        $url = $this->fill((string) $platform->url, array_map('strval', $platform->options));
        $query = [];

        foreach ($platform->query as $parameter => $template) {
            $value = trim($this->fill($template, $values));

            if ($value !== '') {
                $query[$parameter] = $value;
            }
        }

        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') && $platform->separator === '?' ? '&' : $platform->separator;

        return $url.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The shared address with campaign tags for this platform. Parameters the
     * address already has are kept; the fragment stays at the end.
     */
    public function tag(string $url, Platform|string $platform, ?string $medium = null): string
    {
        if ($this->utm === [] || ! preg_match('~^https?://~i', $url)) {
            return $url;
        }

        $key = $platform instanceof Platform ? $platform->key : $platform;
        $medium ??= $platform instanceof Platform ? $platform->medium : 'social';

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        parse_str((string) parse_url($base, PHP_URL_QUERY), $existing);

        $add = [];
        foreach ($this->utm as $parameter => $template) {
            if (! array_key_exists($parameter, $existing)) {
                $add[$parameter] = strtr($template, ['{platform}' => $key, '{medium}' => $medium]);
            }
        }

        if ($add === []) {
            return $url;
        }

        $base .= (str_contains($base, '?') ? (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&') : '?')
            .http_build_query($add, '', '&', PHP_QUERY_RFC3986);

        return $fragment === null ? $base : $base.'#'.$fragment;
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(ShareData $share, Platform $platform): array
    {
        $url = (string) $share->url;
        $templates = implode(' ', $platform->query);
        $message = (string) $share->message();
        $title = (string) $share->title;
        $hashtags = $share->hashtags;

        if ($platform->limit !== null) {
            [$message, $title] = $this->fit($platform, $templates, $message, $title, $url, $share->via, $hashtags);
        }

        $join = fn (string ...$parts) => implode("\n\n", array_filter($parts, fn ($part) => $part !== ''));

        return array_map('strval', $platform->options) + [
            'url' => $url,
            'title' => $title,
            'message' => $message,
            'text' => (string) $share->text,
            'body' => $join($message, $url),
            'mail_body' => $join((string) ($share->text ?? $share->message()), $url),
            'image' => (string) $share->image,
            'via' => (string) $share->via,
            'hashtags' => implode(',', $hashtags),
            'hashtag_line' => implode(' ', array_map(fn ($tag) => '#'.$tag, $hashtags)),
            'first_hashtag' => $hashtags === [] ? '' : '#'.$hashtags[0],
            'tags' => implode(',', $hashtags),
            'site' => (string) $share->siteName,
        ];
    }

    /**
     * Shortens the message (and title) so the whole post fits the platform's
     * limit, counting the link, "via @handle" and hashtags the way it does.
     *
     * @param  list<string>  $hashtags
     * @return array{0: string, 1: string}
     */
    private function fit(Platform $platform, string $templates, string $message, string $title, string $url, ?string $via, array $hashtags): array
    {
        $limit = (int) $platform->limit;
        $link = $url === '' ? 0 : ($platform->linkWeight ?? mb_strlen($url)) + 2;
        $overhead = 0;

        if (str_contains($templates, '{url}') || str_contains($templates, '{body}')) {
            $overhead += $link;
        }

        if ($via && str_contains($templates, '{via}')) {
            $overhead += mb_strlen(' via @'.$via);
        }

        if ($hashtags !== [] && (str_contains($templates, '{hashtags}') || str_contains($templates, '{hashtag_line}'))) {
            $overhead += array_sum(array_map(fn ($tag) => mb_strlen($tag) + 2, $hashtags));
        }

        $budget = max(0, $limit - $overhead);

        return [
            Text::limit($message, $budget),
            str_contains($templates, '{message}') || str_contains($templates, '{body}') ? $title : Text::limit($title, $limit),
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function fill(string $template, array $values): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn (array $match) => $values[$match[1]] ?? '', $template);
    }
}
