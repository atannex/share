<?php

declare(strict_types=1);

namespace Atannex\Share\Links;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * A share's links, keyed by platform, in the order they were asked for.
 *
 * @extends Collection<string, Link>
 */
final class LinkCollection extends Collection implements Htmlable
{
    /** Links the server can follow (web platforms and app schemes). */
    public function addresses(): static
    {
        return $this->filter(fn (Link $link) => $link->href !== null);
    }

    /** Copy, native, QR, embed and print. */
    public function actions(): static
    {
        return $this->filter(fn (Link $link) => $link->action() !== null);
    }

    /**
     * platform => the platform's own share address.
     *
     * @return array<string, string>
     */
    public function targets(): array
    {
        return $this->map(fn (Link $link) => $link->target)->filter()->all();
    }

    public function toHtml(): string
    {
        return $this->map(fn (Link $link) => $link->toHtml())->implode("\n");
    }
}
