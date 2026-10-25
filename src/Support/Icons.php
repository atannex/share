<?php

declare(strict_types=1);

namespace Atannex\Share\Support;

use Atannex\Share\Platforms\Platform;

/**
 * Inline SVG marks (resources/icons.php). A platform's icon may also be raw
 * <svg> markup or a CSS class for an icon font ("fab fa-linkedin-in").
 */
final class Icons
{
    /** @var array<string, array{0: string, 1: string}>|null */
    private static ?array $set = null;

    public static function for(Platform $platform): string
    {
        return self::render($platform->icon ?? $platform->key, mb_strtoupper(mb_substr($platform->label, 0, 1)));
    }

    /**
     * @param  string  $initial  drawn when there is no icon of that name
     */
    public static function render(string $icon, string $initial = '?'): string
    {
        if (str_starts_with(ltrim($icon), '<')) {
            return $icon;
        }

        $set = self::$set ??= require dirname(__DIR__, 2).'/resources/icons.php';

        if (isset($set[$icon])) {
            [$box, $path] = $set[$icon];

            return '<svg class="share-icon" viewBox="'.$box.'" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"><path d="'.$path.'"/></svg>';
        }

        if (preg_match('/^[\w\- ]+$/', $icon) && str_contains($icon, ' ')) {
            return '<i class="share-icon '.e($icon).'" aria-hidden="true"></i>';
        }

        return '<svg class="share-icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><text x="12" y="17" text-anchor="middle" font-size="15" font-weight="700" fill="currentColor" font-family="system-ui,sans-serif">'.e($initial).'</text></svg>';
    }

    public static function has(string $icon): bool
    {
        $set = self::$set ??= require dirname(__DIR__, 2).'/resources/icons.php';

        return isset($set[$icon]);
    }
}
