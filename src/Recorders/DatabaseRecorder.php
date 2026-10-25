<?php

declare(strict_types=1);

namespace Atannex\Share\Recorders;

use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Events\Shared;
use Atannex\Share\Models\ShareCount;
use Illuminate\Support\Facades\DB;

/**
 * One row per address, platform and day in share_counts, incremented in a
 * single upsert so concurrent shares never lose a count.
 */
final class DatabaseRecorder implements Recorder
{
    public function record(Shared $share): void
    {
        $model = new ShareCount;
        $now = $model->freshTimestampString();
        $key = $share->url ?? $share->subject?->reference() ?? '';

        $model->getConnection()->table($model->getTable())->upsert(
            [[
                'url_hash' => sha1($key),
                'url' => $share->url,
                'shareable_type' => $share->subject?->type,
                'shareable_id' => $share->subject?->id,
                'platform' => $share->platform,
                'date' => now()->toDateString(),
                'total' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['url_hash', 'platform', 'date'],
            [
                'total' => DB::raw($model->getConnection()->getQueryGrammar()->wrap('total').' + 1'),
                'updated_at' => $now,
            ],
        );
    }
}
