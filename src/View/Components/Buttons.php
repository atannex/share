<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Atannex\Share\PendingShare;
use Illuminate\Contracts\View\View;

/**
 * A row (or column) of share buttons:
 *
 *     <x-share::buttons :for="$post" variant="brand" only="facebook,x,whatsapp,copy" sheet="share-sheet" />
 *
 * variant: icons (round), pills (icon and name), brand (filled in each
 * platform's colour), minimal (text links). sheet: the id of a
 * <x-share::sheet> that a "More" button opens.
 */
final class Buttons extends ShareComponent
{
    /**
     * @param  string|list<string>|null  $only
     * @param  string|list<string>|null  $except
     */
    public function __construct(
        mixed $for = null,
        ?PendingShare $share = null,
        string|array|null $only = null,
        string|array|null $except = null,
        ?string $preset = null,
        public string $variant = 'icons',
        public string $size = 'md',
        public ?bool $labels = null,
        public ?string $sheet = null,
        public ?string $heading = null,
        public bool $vertical = false,
    ) {
        parent::__construct($for, $share, $only, $except, $preset);

        $this->labels ??= in_array($variant, ['pills', 'minimal'], true);
    }

    public function render(): View
    {
        return view('share::buttons', ['share' => $this->pending, 'links' => $this->pending->links()]);
    }
}
