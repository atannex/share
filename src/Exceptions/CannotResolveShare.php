<?php

declare(strict_types=1);

namespace Atannex\Share\Exceptions;

final class CannotResolveShare extends ShareException
{
    public static function for(mixed $subject): self
    {
        return new self(sprintf(
            'Cannot share [%s]: implement Atannex\Share\Contracts\Shareable, add #[Atannex\Share\Attributes\Shareable(...)], or register a resolver with Share::resolveUsing().',
            get_debug_type($subject),
        ));
    }

    public static function withoutUrl(object $model): self
    {
        return new self(sprintf(
            'Cannot work out the address of [%s]. Give #[Shareable(route: ...)] or #[Shareable(url: ...)], or a url attribute.',
            $model::class,
        ));
    }
}
