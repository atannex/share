<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;

/**
 * The default look (resources/css/share.css), inline. Every colour, size and
 * radius is a --share-* custom property, so a theme overrides a handful of
 * variables instead of the rules. Leave it out to style from scratch.
 */
final class Styles extends Component
{
    private static ?string $source = null;

    public function __construct(public ?string $nonce = null)
    {
        $this->nonce ??= class_exists(Vite::class) ? Vite::cspNonce() : null;
    }

    public function render(): View
    {
        return view('share::styles', [
            'source' => self::$source ??= (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/share.css'),
        ]);
    }
}
