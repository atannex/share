<?php

declare(strict_types=1);

namespace Atannex\Share\Contracts;

use Atannex\Share\ShareData;

/**
 * Anything that knows how it is shared. Return a ShareData, or an array of
 * its fields (url, title, message, text, image, hashtags, via, video, files,
 * embed…), which is cleaned up the same way as everything else.
 */
interface Shareable
{
    /**
     * @return ShareData|array<string, mixed>
     */
    public function toShare(): ShareData|array;
}
