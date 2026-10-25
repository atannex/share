<?php

declare(strict_types=1);

namespace Atannex\Share\Platforms;

use Atannex\Share\Exceptions\UnknownPlatform;

/**
 * Every platform the app can share to: the built-in catalog, the app's
 * config('share.custom') and anything added with Share::extend().
 */
final class PlatformRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $definitions;

    /** @var array<string, Platform> */
    private array $built = [];

    /** @var array<string, string> alias => key */
    private array $aliases = [];

    /**
     * @param  array<string, array<string, mixed>>  $catalog
     * @param  array<string, array<string, mixed>>  $custom
     * @param  array{0: int, 1: int}  $popup
     */
    public function __construct(array $catalog, array $custom = [], private readonly array $popup = [620, 680])
    {
        $this->definitions = $catalog;

        foreach ($custom as $key => $definition) {
            $this->define((string) $key, (array) $definition);
        }
    }

    public static function catalog(): array
    {
        return require dirname(__DIR__, 2).'/resources/platforms.php';
    }

    /**
     * Add a platform, or change a built-in one (merged key by key).
     *
     * @param  array<string, mixed>|Platform  $definition
     */
    public function define(string $key, array|Platform $definition): void
    {
        unset($this->built[$key]);
        $this->aliases = [];

        if ($definition instanceof Platform) {
            $this->built[$key] = $definition;
            $this->definitions[$key] = ['label' => $definition->label, 'kind' => $definition->kind];

            return;
        }

        $this->definitions[$key] = array_replace($this->definitions[$key] ?? [], $definition);
    }

    public function has(string $key): bool
    {
        return $this->key($key) !== null;
    }

    /**
     * The platform's key for a key or an alias ("twitter" => "x").
     */
    public function key(string $key): ?string
    {
        $key = strtolower($key);

        if (isset($this->definitions[$key])) {
            return $key;
        }

        if ($this->aliases === []) {
            foreach ($this->all() as $platform) {
                foreach ($platform->aliases as $alias) {
                    $this->aliases[$alias] = $platform->key;
                }
            }
        }

        return $this->aliases[$key] ?? null;
    }

    /**
     * @throws UnknownPlatform
     */
    public function get(string $key): Platform
    {
        $resolved = $this->key($key) ?? throw UnknownPlatform::named($key, array_keys($this->definitions));

        return $this->built[$resolved] ??= Platform::fromArray($resolved, $this->definitions[$resolved], $this->popup);
    }

    /**
     * @return array<string, Platform>
     */
    public function all(): array
    {
        $all = [];

        foreach (array_keys($this->definitions) as $key) {
            $all[$key] = $this->built[$key] ??= Platform::fromArray($key, $this->definitions[$key], $this->popup);
        }

        return $all;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }
}
