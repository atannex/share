<?php

declare(strict_types=1);

namespace Atannex\Share\Console;

use Atannex\Share\Platforms\Platform;
use Atannex\Share\ShareManager;
use Illuminate\Console\Command;

final class PlatformsCommand extends Command
{
    protected $signature = 'share:platforms {--enabled : Only the platforms offered by default}';

    protected $description = 'List the platforms you can share to';

    public function handle(ShareManager $share): int
    {
        $enabled = $share->select();
        $platforms = $share->platforms()->all();

        if ($this->option('enabled')) {
            $platforms = array_intersect_key($platforms, array_flip($enabled));
        }

        $this->table(
            ['Key', 'Name', 'Kind', 'Needs', 'Limit', 'Default', 'Aliases'],
            array_map(fn (Platform $platform) => [
                $platform->key,
                $platform->label,
                $platform->kind.($platform->mobile ? ' (phones)' : ''),
                implode(', ', $platform->requires) ?: '-',
                $platform->limit ?? '-',
                in_array($platform->key, $enabled, true) ? '#'.(array_search($platform->key, $enabled, true) + 1) : '',
                implode(', ', $platform->aliases),
            ], array_values($platforms)),
        );

        $this->components->info(count($platforms).' platforms; '.count($enabled).' offered by default (config share.platforms).');

        return self::SUCCESS;
    }
}
