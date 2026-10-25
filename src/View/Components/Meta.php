<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Illuminate\Contracts\View\View;

/**
 * Open Graph and X card tags, so shared links unfurl with a title, a
 * description, an image (and a player for video):
 *
 *     <head> … <x-share::meta :for="$post" /> </head>
 */
final class Meta extends ShareComponent
{
    public function render(): View
    {
        return view('share::meta', ['share' => $this->pending->data()]);
    }
}
