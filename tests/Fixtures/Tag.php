<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
