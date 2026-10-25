<?php

declare(strict_types=1);

namespace Atannex\Share\Exceptions;

use InvalidArgumentException;

final class UnknownPlatform extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known
     */
    public static function named(string $key, array $known): self
    {
        return new self(sprintf('Unknown share platform [%s]. Known: %s.', $key, implode(', ', $known)));
    }
}
