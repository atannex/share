<?php

declare(strict_types=1);

namespace Atannex\Share\View\Components;

use Atannex\Share\PendingShare;
use Atannex\Share\ShareManager;
use Illuminate\View\Component;

/**
 * Shared by the share components: :for="$anything" or :share="Share::for(...)",
 * plus only="x,whatsapp", except="…" and preset="compact".
 */
abstract class ShareComponent extends Component
{
    public PendingShare $pending;

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
    ) {
        $this->pending = $share ?? app(ShareManager::class)->for($for);

        if ($preset !== null) {
            $this->pending->preset($preset);
        }
        if ($only !== null) {
            $this->pending->only($only);
        }
        if ($except !== null) {
            $this->pending->except($except);
        }
    }
}
