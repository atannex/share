<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Fixtures;

use Atannex\Share\Attributes\ShareAs;
use Atannex\Share\Concerns\HasShares;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ShareAs(route: 'stories.show', routeKey: 'slug', text: 'excerpt', image: 'cover_url', hashtags: 'tags', type: 'article')]
class Article extends Model
{
    use HasShares;

    protected $guarded = [];

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
