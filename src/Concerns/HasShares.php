<?php

declare(strict_types=1);

namespace Atannex\Share\Concerns;

use Atannex\Share\Models\ShareCount;
use Atannex\Share\PendingShare;
use Atannex\Share\ShareManager;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For shareable models: $post->share()->links(), $post->shareCount('x'),
 * Post::withShareCount()->orderByDesc('shares_count').
 *
 * Map fields with #[ShareAs(...)] or implement Contracts\Shareable; with
 * neither, common field names are used.
 */
trait HasShares
{
    public function share(): PendingShare
    {
        return app(ShareManager::class)->for($this);
    }

    /**
     * Daily counts from the database recorder.
     */
    public function shareCounts(): MorphMany
    {
        return $this->morphMany(ShareCount::class, 'shareable');
    }

    /**
     * @param  string|list<string>|null  $platform
     */
    public function shareCount(string|array|null $platform = null, ?DateTimeInterface $since = null): int
    {
        return app(ShareManager::class)->count($this, $platform, $since);
    }

    /**
     * @return array<string, int> platform => shares, most first
     */
    public function shareBreakdown(?DateTimeInterface $since = null): array
    {
        return app(ShareManager::class)->breakdown($this, $since);
    }

    /**
     * Adds shares_count (all platforms, all time).
     */
    public function scopeWithShareCount(Builder $query): void
    {
        $query->withSum('shareCounts as shares_count', 'total');
    }
}
