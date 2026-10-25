<?php

declare(strict_types=1);

use Atannex\Share\PendingShare;
use Atannex\Share\ShareManager;

if (! function_exists('share')) {
    /**
     * share($post)->links(), share('https://…')->to('x'), share() for the current page.
     */
    function share(mixed $subject = null): PendingShare
    {
        return app(ShareManager::class)->for($subject);
    }
}
