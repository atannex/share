<?php

declare(strict_types=1);

namespace Atannex\Share\Models;

use Atannex\Share\Subject;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Shares of one address on one platform on one day (the database recorder).
 *
 * @property string $url_hash
 * @property ?string $url
 * @property ?string $shareable_type
 * @property ?string $shareable_id
 * @property string $platform
 * @property Carbon $date
 * @property int $total
 */
class ShareCount extends Model
{
    protected $table = 'share_counts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'integer'];
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Rows about a model, a Subject, or an address.
     */
    public function scopeAbout(Builder $query, Model|Subject|string $subject): void
    {
        if (is_string($subject)) {
            $query->where('url_hash', sha1($subject));

            return;
        }

        $subject = $subject instanceof Model ? Subject::of($subject) : $subject;
        $query->where('shareable_type', $subject->type)->where('shareable_id', $subject->id);
    }

    public function scopeOn(Builder $query, string ...$platforms): void
    {
        $query->whereIn('platform', $platforms);
    }

    public function scopeBetween(Builder $query, ?DateTimeInterface $from, ?DateTimeInterface $to = null): void
    {
        $query
            ->when($from, fn (Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('date', '<=', $to));
    }
}
