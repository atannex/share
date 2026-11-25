<?php

declare(strict_types=1);

namespace Atannex\Share\Relations;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * $model->shareCounts, for any key type.
 *
 * share_counts.shareable_id is a string column (it holds integer, UUID and
 * ULID keys alike), and PostgreSQL won't compare it with an integer. Keys are
 * passed as strings, so lazy and eager loading work on every database.
 *
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends MorphMany<\Atannex\Share\Models\ShareCount, TDeclaringModel>
 */
final class ShareCounts extends MorphMany
{
    public function getParentKey()
    {
        $key = parent::getParentKey();

        return $key === null ? null : (string) $key;
    }

    public function addEagerConstraints(array $models)
    {
        // whereIn with strings, not the whereIntegerInRaw Laravel picks for integer keys.
        $this->whereInEager(
            'whereIn',
            $this->foreignKey,
            array_map('strval', $this->getKeys($models, $this->localKey)),
            $this->getRelationQuery(),
        );

        $this->getRelationQuery()->where($this->morphType, $this->morphClass);
    }
}
