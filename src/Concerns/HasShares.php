<?php

declare(strict_types=1);

namespace Atannex\Share\Concerns;

use Atannex\Share\Models\ShareCount;
use Atannex\Share\PendingShare;
use Atannex\Share\Relations\ShareCounts;
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
        // morphMany(ShareCount::class, 'shareable'), with keys compared as strings (see ShareCounts).
        $related = $this->newRelatedInstance(ShareCount::class);
        $table = $related->getTable();

        return new ShareCounts($related->newQuery(), $this, $table.'.shareable_type', $table.'.shareable_id', $this->getKeyName());
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
        $model = $query->getModel();
        $grammar = $query->getQuery()->getGrammar();
        $key = $grammar->wrap($model->getQualifiedKeyName());
        // shareable_id is a string column; PostgreSQL won't compare it with an integer key.
        $key = $model->getConnection()->getDriverName() === 'pgsql' ? "{$key}::text" : $key;

        if ($query->getQuery()->columns === null) {
            $query->select($model->qualifyColumn('*'));
        }

        // coalesce: models never shared get 0, not null (which PostgreSQL sorts first).
        $query->addSelect(['shares_count' => ShareCount::query()
            ->selectRaw('coalesce(sum('.$grammar->wrap('total').'), 0)')
            ->where('shareable_type', $model->getMorphClass())
            ->whereRaw($grammar->wrap((new ShareCount)->qualifyColumn('shareable_id')).' = '.$key)]);
    }
}
