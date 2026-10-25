<?php

declare(strict_types=1);

namespace Atannex\Share\Recorders;

use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Events\Shared;

/** Counts nothing; the Shared event still fires. */
final class NullRecorder implements Recorder
{
    public function record(Shared $share): void {}
}
