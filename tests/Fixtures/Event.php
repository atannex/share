<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Fixtures;

use Atannex\Share\Contracts\Shareable;
use Atannex\Share\ShareData;

/** Not a model: describes its own share. */
final class Event implements Shareable
{
    public function __construct(public string $name, public string $slug) {}

    public function toShare(): ShareData|array
    {
        return [
            'url' => '/events/'.$this->slug,
            'title' => $this->name,
            'hashtags' => ['live events', 'Bamenda'],
        ];
    }
}
