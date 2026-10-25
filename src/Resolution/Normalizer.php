<?php

declare(strict_types=1);

namespace Atannex\Share\Resolution;

use Atannex\Share\ShareData;
use Atannex\Share\Subject;
use Atannex\Share\Support\Text;
use BackedEnum;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stringable;

/**
 * Turns loose values into clean share fields: plain text from HTML,
 * absolute addresses, hashtags without "#" or spaces, media objects to URLs.
 */
final class Normalizer
{
    public function __construct(
        private readonly UrlGenerator $urls,
        private readonly int $textLimit = 200,
        private readonly int $hashtagLimit = 2,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function apply(ShareData $share, array $values): ShareData
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = $this->value((string) $key, $value);
        }

        return $share->with($clean);
    }

    public function value(string $field, mixed $value): mixed
    {
        return match ($field) {
            'url', 'link', 'href', 'image' => $this->address($value),
            'title', 'name', 'headline', 'message', 'image_alt', 'imageAlt', 'site_name', 'siteName' => $this->plain($value),
            'text', 'description', 'summary', 'excerpt' => $this->text($value),
            'hashtags', 'tags' => $this->hashtags($value),
            'via' => ($via = $this->plain($value)) === null ? null : (ltrim($via, '@') ?: null),
            'files' => $this->files($value),
            'video' => $this->video($value),
            'subject' => $value instanceof Model ? Subject::of($value) : ($value instanceof Subject ? $value : null),
            'image_width', 'imageWidth', 'image_height', 'imageHeight' => $value === null ? null : (int) $value,
            'meta' => array_map('strval', (array) $value),
            default => $value instanceof BackedEnum ? $value->value : $value,
        };
    }

    /**
     * An absolute address. Accepts media objects (getFullUrl()/getUrl()) and Stringables.
     */
    public function address(mixed $value): ?string
    {
        if (is_object($value)) {
            $value = match (true) {
                method_exists($value, 'getFullUrl') => $value->getFullUrl(),
                method_exists($value, 'getUrl') => $value->getUrl(),
                $value instanceof Stringable => (string) $value,
                default => null,
            };
        }

        if (! is_string($value) || ($value = trim($value)) === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }

        return preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) ? $value : $this->urls->to($value);
    }

    /**
     * One line of plain text: tags stripped, entities decoded, spaces collapsed.
     */
    public function plain(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            return null;
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    public function text(mixed $value): ?string
    {
        $text = $this->plain($value);

        return $text === null || $this->textLimit <= 0 ? $text : Text::limit($text, $this->textLimit);
    }

    /**
     * "Land use, #Lebialem" or ['land use', $tagModel] => ['LandUse', 'Lebialem'].
     *
     * @return list<string>
     */
    public function hashtags(mixed $value): array
    {
        $tags = [];

        foreach ($this->flatten($value) as $tag) {
            // Tag models and the like: their name (models are Stringable too, as JSON).
            if (is_object($tag)) {
                $tag = data_get($tag, 'name') ?? data_get($tag, 'title') ?? ($tag instanceof Stringable && ! $tag instanceof Model ? (string) $tag : null);
            }

            $tag = $this->plain($tag);
            if ($tag === null) {
                continue;
            }

            // Several words become one ("land use" => "LandUse"); a single word keeps its case.
            $tag = str_contains($tag, ' ') ? Str::studly(Str::lower($tag)) : $tag;
            $tag = (string) preg_replace('/[^\pL\pN_]+/u', '', $tag);

            if ($tag !== '' && ! in_array(Str::lower($tag), array_map(Str::lower(...), $tags), true)) {
                $tags[] = $tag;
            }
        }

        return $this->hashtagLimit > 0 ? array_slice($tags, 0, $this->hashtagLimit) : [];
    }

    /**
     * Strings split on commas and "#", iterables (collections, nested arrays) unpacked.
     *
     * @return list<mixed>
     */
    private function flatten(mixed $value): array
    {
        if (is_string($value)) {
            return preg_split('/[,#]+/', $value) ?: [];
        }

        if (! is_iterable($value)) {
            return [$value];
        }

        $flat = [];
        foreach ($value as $item) {
            array_push($flat, ...(is_iterable($item) || is_string($item) ? $this->flatten($item) : [$item]));
        }

        return $flat;
    }

    /**
     * @return list<array{url: string, mime: ?string, name: ?string, size: ?int}>
     */
    private function files(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $value = is_array($value) && (isset($value['url']) || ! array_is_list($value)) ? [$value] : (array) $value;
        $files = [];

        foreach ($value as $file) {
            $url = $this->address(is_array($file) ? ($file['url'] ?? null) : $file);

            if ($url === null) {
                continue;
            }

            $files[] = [
                'url' => $url,
                'mime' => is_array($file) ? ($file['mime'] ?? null) : (is_object($file) ? data_get($file, 'mime_type') : null),
                'name' => is_array($file) ? ($file['name'] ?? basename((string) parse_url($url, PHP_URL_PATH))) : basename((string) parse_url($url, PHP_URL_PATH)),
                'size' => is_array($file) ? (isset($file['size']) ? (int) $file['size'] : null) : (is_object($file) ? data_get($file, 'size') : null),
            ];
        }

        return $files;
    }

    /**
     * @return array{url: string, mime: ?string, width: ?int, height: ?int, player: ?string}|null
     */
    private function video(mixed $value): ?array
    {
        $data = is_array($value) ? $value : ['url' => $value];
        $url = $this->address($data['url'] ?? null);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'mime' => $data['mime'] ?? (is_object($value) ? data_get($value, 'mime_type') : null) ?? 'video/mp4',
            'width' => isset($data['width']) ? (int) $data['width'] : null,
            'height' => isset($data['height']) ? (int) $data['height'] : null,
            'player' => $this->address($data['player'] ?? null),
        ];
    }
}
