<?php

declare(strict_types=1);

namespace Atannex\Share;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * What is being shared, whatever it came from: a model, an address, a piece
 * of text, an array. Immutable; with() returns a changed copy.
 *
 * @implements Arrayable<string, mixed>
 */
final class ShareData implements Arrayable, JsonSerializable
{
    /** Field names other code tends to use. */
    private const ALIASES = [
        'link' => 'url',
        'href' => 'url',
        'name' => 'title',
        'headline' => 'title',
        'description' => 'text',
        'summary' => 'text',
        'excerpt' => 'text',
        'tags' => 'hashtags',
    ];

    /**
     * @param  list<string>  $hashtags  without "#"
     * @param  list<array{url: string, mime: ?string, name: ?string, size: ?int}>  $files  shared as files by the device's share sheet
     * @param  array{url: string, mime: ?string, width: ?int, height: ?int, player: ?string}|null  $video
     * @param  array<string, string>  $meta  extra meta tags for <x-share::meta> (property => content)
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $title = null,
        public readonly ?string $message = null,
        public readonly ?string $text = null,
        public readonly ?string $image = null,
        public readonly ?string $imageAlt = null,
        public readonly ?int $imageWidth = null,
        public readonly ?int $imageHeight = null,
        public readonly array $hashtags = [],
        public readonly ?string $via = null,
        public readonly ?string $siteName = null,
        public readonly ?string $type = null,
        public readonly ?string $locale = null,
        public readonly ?array $video = null,
        public readonly array $files = [],
        public readonly ?string $embed = null,
        public readonly ?Subject $subject = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $values  any of the constructor's names (snake_case works too)
     */
    public static function make(array $values = []): self
    {
        return (new self)->with($values);
    }

    /**
     * A copy with these values changed. Unknown keys are an error, so a typo
     * doesn't silently drop a value.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $values = get_object_vars($this);

        foreach ($changes as $key => $value) {
            $key = self::ALIASES[$key] ?? lcfirst(str_replace('_', '', ucwords((string) $key, '_')));

            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Unknown share field [{$key}].");
            }

            $values[$key] = $value;
        }

        return new self(...$values);
    }

    /**
     * True when the share has the field ("files" and "hashtags" when not empty).
     */
    public function has(string $field): bool
    {
        $value = match ($field) {
            'message' => $this->message ?? $this->title,
            default => property_exists($this, $field) ? $this->{$field} : null,
        };

        return is_array($value) ? $value !== [] : $value !== null && $value !== '';
    }

    /**
     * The post text: the message given, else the title.
     */
    public function message(): ?string
    {
        return $this->message ?? $this->title;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'message' => $this->message,
            'text' => $this->text,
            'image' => $this->image,
            'image_alt' => $this->imageAlt,
            'image_width' => $this->imageWidth,
            'image_height' => $this->imageHeight,
            'hashtags' => $this->hashtags,
            'via' => $this->via,
            'site_name' => $this->siteName,
            'type' => $this->type,
            'locale' => $this->locale,
            'video' => $this->video,
            'files' => $this->files,
            'embed' => $this->embed,
            'subject' => $this->subject?->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
