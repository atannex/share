<?php

declare(strict_types=1);

namespace Atannex\Share\Recorders;

use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Events\Shared;
use Closure;

/** Share::recordUsing(fn (Shared $share) => ...). */
final class CallbackRecorder implements Recorder
{
    /**
     * @param  Closure(Shared): mixed  $callback
     */
    public function __construct(private readonly Closure $callback) {}

    public function record(Shared $share): void
    {
        ($this->callback)($share);
    }
}
