<?php

declare(strict_types=1);

namespace Atannex\Share\Events;

use Atannex\Share\Subject;
use Illuminate\Database\Eloquent\Model;

/**
 * A reader shared something (counted once per reader, platform and address
 * within config('share.tracking.dedupe') minutes).
 */
final class Shared
{
    private ?Model $model = null;

    private bool $loaded = false;

    public function __construct(
        public readonly string $platform,
        public readonly ?string $url,
        public readonly ?Subject $subject = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $referrer = null,
        public readonly int|string|null $userId = null,
    ) {}

    /**
     * The shared model, when the share was about one (queried once).
     */
    public function model(): ?Model
    {
        if (! $this->loaded) {
            $this->model = $this->subject?->resolve();
            $this->loaded = true;
        }

        return $this->model;
    }
}
