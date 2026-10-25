<?php

declare(strict_types=1);

namespace Atannex\Share\Links;

use Atannex\Share\Platforms\Platform;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;

/**
 * One way to share one thing: where the button points and how it looks.
 *
 *   href    what the button opens: the counted redirect for web platforms,
 *           the address itself for app schemes, null for browser actions
 *   target  the platform's own address (null for browser actions)
 *   action  for browser actions: copy, native, qr, embed or print
 *
 * @implements Arrayable<string, mixed>
 */
final class Link implements Arrayable, Htmlable, JsonSerializable
{
    public function __construct(
        public readonly Platform $platform,
        public readonly ?string $href,
        public readonly ?string $target,
        public readonly ?string $value,
        public readonly string $label,
        public readonly string $icon,
    ) {}

    public function key(): string
    {
        return $this->platform->key;
    }

    public function action(): ?string
    {
        return $this->platform->isClient() ? $this->platform->key : null;
    }

    /**
     * Counted by the page script's beacon (the server can't see the click).
     */
    public function beacon(): bool
    {
        return ! $this->platform->redirects();
    }

    /**
     * The attributes the page script reads, for building your own markup.
     *
     * @return array<string, string|bool>
     */
    public function attributes(): array
    {
        $platform = $this->platform;
        $attributes = [
            'data-share-platform' => $platform->key,
            'aria-label' => __('share::share.share_on', ['platform' => $this->label]),
            'style' => sprintf('--share-brand: %s; --share-brand-fg: %s', $platform->color, $platform->foreground ?? '#ffffff'),
        ];

        if ($this->href !== null) {
            $attributes['href'] = $this->href;
            $attributes['rel'] = 'nofollow noopener noreferrer';
            if (! in_array(parse_url($this->href, PHP_URL_SCHEME), ['mailto', 'sms'], true)) {
                $attributes['target'] = '_blank';
            }
        }

        if ($this->action() !== null) {
            $attributes['data-share-action'] = $this->action();
        }

        if ($this->value !== null) {
            $attributes['data-share-value'] = $this->value;
        }

        if ($platform->popup !== false && $this->href !== null && $platform->isWeb()) {
            $attributes['data-share-popup'] = $platform->popup[0].'x'.$platform->popup[1];
        }

        if ($this->beacon()) {
            $attributes['data-share-beacon'] = true;
        }

        if ($platform->mobile) {
            $attributes['data-share-mobile'] = true;
        }

        return $attributes;
    }

    /**
     * A plain <a> (or <button> for browser actions) with the icon and label.
     */
    public function toHtml(): string
    {
        $tag = $this->href === null ? 'button' : 'a';
        $attributes = $this->attributes() + ($tag === 'button' ? ['type' => 'button'] : []);

        return sprintf('<%1$s %2$s>%3$s<span>%4$s</span></%1$s>', $tag, self::attributeString($attributes), $this->icon, e($this->label));
    }

    /**
     * @param  array<string, string|bool>  $attributes
     */
    public static function attributeString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if ($value === false) {
                continue;
            }
            $parts[] = $value === true ? $name : $name.'="'.e($value).'"';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->key,
            'label' => $this->label,
            'kind' => $this->platform->kind,
            'href' => $this->href,
            'target' => $this->target,
            'value' => $this->value,
            'action' => $this->action(),
            'color' => $this->platform->color,
            'popup' => $this->platform->popup,
            'mobile' => $this->platform->mobile,
            'beacon' => $this->beacon(),
            'icon' => $this->icon,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
