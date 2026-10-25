<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Fixtures;

use Atannex\Share\Concerns\HasShares;
use Illuminate\Database\Eloquent\Model;

/** No mapping: shared from its name, description and url columns. */
class Person extends Model
{
    use HasShares;

    protected $table = 'people';

    protected $guarded = [];
}
