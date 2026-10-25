<?php

declare(strict_types=1);

namespace Atannex\Share\Attributes;

use Attribute;

/**
 * Maps a model's fields onto a share, so it needs no code of its own:
 *
 *     #[ShareAs(route: 'posts.show', routeKey: 'slug', text: 'excerpt', image: 'cover_url', hashtags: 'tags.*.name')]
 *     class Post extends Model { use HasShares; }
 *
 * Each value is an attribute, a method or a dot path on the model
 * (data_get). Fields left null are guessed from common names (title, name,
 * description, summary, image, url…).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ShareAs
{
    /**
     * @param  string|null  $route  a named route the model's address is made with
     * @param  string|null  $routeKey  the field passed to the route (default the route key)
     * @param  string|null  $routeParameter  the route parameter it fills (default: the route's first)
     */
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $route = null,
        public readonly ?string $routeKey = null,
        public readonly ?string $routeParameter = null,
        public readonly ?string $title = null,
        public readonly ?string $message = null,
        public readonly ?string $text = null,
        public readonly ?string $image = null,
        public readonly ?string $hashtags = null,
        public readonly ?string $via = null,
        public readonly ?string $type = null,
    ) {}
}
