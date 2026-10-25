<?php

declare(strict_types=1);

namespace Atannex\Share\Contracts;

use Atannex\Share\Events\Shared;

/**
 * Keeps count of shares. Called once per counted share, after prefetches,
 * bots and repeats have been filtered out.
 */
interface Recorder
{
    public function record(Shared $share): void;
}
