<?php

declare(strict_types=1);

namespace Atannex\Share\Platforms;

use Atannex\Share\ShareData;
use Closure;
use InvalidArgumentException;

/**
 * One place to share to, built from its definition (resources/platforms.php,
 * config('share.custom') or Share::extend()).
 */
final class Platform
{
    public const WEB = 'web';

    public const APP = 'app';

    public const CLIENT = 'client';

    /**
     * @param  array<string, string>  $query
     * @param  list<string>  $requires
     * @param  array{0: int, 1: int}|false  $popup
     * @param  list<string>  $aliases
     * @param  array<string, mixed>  $options  every other key of the definition ({instance}…)
     * @param  (Closure(ShareData, Platform): string)|null  $builder  builds the address instead of url + query
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $kind = self::WEB,
        public readonly ?string $url = null,
        public readonly array $query = [],
        public readonly string $separator = '?',
        public readonly array $requires = ['url'],
        public readonly ?int $limit = null,
        public readonly ?int $linkWeight = null,
        public readonly string $medium = 'social',
        public readonly array|false $popup = [620, 680],
        public readonly bool $mobile = false,
        public readonly string $color = '#334155',
        public readonly ?string $foreground = null,
        public readonly ?string $icon = null,
        public readonly array $aliases = [],
        public readonly array $options = [],
        public readonly ?Closure $builder = null,
    ) {
        if (! in_array($kind, [self::WEB, self::APP, self::CLIENT], true)) {
            throw new InvalidArgumentException("Share platform [{$key}] has an unknown kind [{$kind}].");
        }

        if ($kind !== self::CLIENT && $url === null && $builder === null) {
            throw new InvalidArgumentException("Share platform [{$key}] needs a url or a build closure.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{0: int, 1: int}  $popup  the default popup size
     */
    public static function fromArray(string $key, array $definition, array $popup = [620, 680]): self
    {
        $known = ['label', 'kind', 'url', 'query', 'separator', 'requires', 'limit', 'link_weight', 'medium', 'popup', 'mobile', 'color', 'foreground', 'icon', 'aliases', 'build'];
        $kind = $definition['kind'] ?? self::WEB;

        $size = $definition['popup'] ?? ($kind === self::WEB ? $popup : false);
        if ($size === true) {
            $size = $popup;
        }

        return new self(
            key: $key,
            label: (string) ($definition['label'] ?? ucfirst($key)),
            kind: $kind,
            url: $definition['url'] ?? null,
            query: array_map('strval', (array) ($definition['query'] ?? [])),
            separator: (string) ($definition['separator'] ?? '?'),
            requires: array_values((array) ($definition['requires'] ?? ($kind === self::CLIENT ? [] : ['url']))),
            limit: isset($definition['limit']) ? (int) $definition['limit'] : null,
            linkWeight: isset($definition['link_weight']) ? (int) $definition['link_weight'] : null,
            medium: (string) ($definition['medium'] ?? 'social'),
            popup: $size === false ? false : [(int) $size[0], (int) $size[1]],
            mobile: (bool) ($definition['mobile'] ?? false),
            color: (string) ($definition['color'] ?? '#334155'),
            foreground: $definition['foreground'] ?? null,
            icon: $definition['icon'] ?? $key,
            aliases: array_values((array) ($definition['aliases'] ?? [])),
            options: array_diff_key($definition, array_flip($known)),
            builder: isset($definition['build']) ? Closure::fromCallable($definition['build']) : null,
        );
    }

    /**
     * Whether the share has everything this platform needs.
     */
    public function supports(ShareData $share): bool
    {
        foreach ($this->requires as $requirement) {
            $any = array_filter(explode('|', $requirement), fn (string $field) => $share->has(trim($field)));

            if ($any === []) {
                return false;
            }
        }

        return true;
    }

    public function isWeb(): bool
    {
        return $this->kind === self::WEB;
    }

    public function isClient(): bool
    {
        return $this->kind === self::CLIENT;
    }

    /**
     * Tracked through the redirect route (an http(s) address the server can send the reader to).
     */
    public function redirects(): bool
    {
        return $this->kind === self::WEB;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
