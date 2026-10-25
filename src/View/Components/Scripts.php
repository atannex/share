<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;

/**
 * The page script (resources/js/share.js), inline, dependency-free, once per
 * page. Picks up the CSP nonce Vite uses, or take nonce="…".
 */
final class Scripts extends Component
{
    private static ?string $source = null;

    public function __construct(public ?string $nonce = null)
    {
        $this->nonce ??= class_exists(Vite::class) ? Vite::cspNonce() : null;
    }

    public function render(): View
    {
        return view('share::scripts', [
            'source' => self::$source ??= (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/share.js'),
            'messages' => [
                'copied' => __('share::share.copied'),
                'copyFailed' => __('share::share.copy_failed'),
                'preparing' => __('share::share.preparing'),
                'ready' => __('share::share.ready'),
                'fileFailed' => __('share::share.file_failed'),
            ],
        ]);
    }
}
