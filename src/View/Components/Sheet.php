<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Atannex\Share\PendingShare;
use Illuminate\Contracts\View\View;

/**
 * The full share dialog: a preview card, every platform, the link to copy,
 * a QR code to carry it to a phone, the embed code and the device's own
 * share sheet. Opened by any [data-share-open="{id}"] button.
 *
 *     <x-share::sheet :for="$post" id="share-sheet" />
 *     <button data-share-open="share-sheet">Share</button>
 */
final class Sheet extends ShareComponent
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
        public string $id = 'share-sheet',
        public ?string $heading = null,
    ) {
        parent::__construct($for, $share, $only, $except, $preset);
    }

    public function render(): View
    {
        $links = $this->pending->links();

        return view('share::sheet', [
            'share' => $this->pending,
            'data' => $this->pending->data(),
            'platforms' => $links->reject(fn ($link) => in_array($link->key(), ['copy', 'native', 'qr', 'embed'], true)),
            'copy' => $links->get('copy') ?? $this->pending->link('copy'),
            'native' => $links->get('native'),
            'qr' => $links->has('qr') ? $this->pending->qr() : null,
            'embed' => $links->get('embed') ?? ($this->pending->data()->embed ? $this->pending->link('embed') : null),
        ]);
    }
}
