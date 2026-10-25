<?php

declare(strict_types=1);

namespace Atannex\Share\Console;

use Atannex\Share\ShareManager;
use Illuminate\Console\Command;

final class LinksCommand extends Command
{
    protected $signature = 'share:links
        {url : The address to share}
        {--title= : Title}
        {--message= : Post text (defaults to the title)}
        {--image= : Image address}
        {--hashtags= : Comma-separated hashtags}
        {--platform=* : Only these platforms (default: all)}
        {--json : Print JSON}';

    protected $description = 'Print the share address of every platform for a link';

    public function handle(ShareManager $share): int
    {
        $pending = $share->for(array_filter([
            'url' => $this->argument('url'),
            'title' => $this->option('title'),
            'message' => $this->option('message'),
            'image' => $this->option('image'),
            'hashtags' => $this->option('hashtags'),
        ]))->withoutTracking()->only($this->option('platform') ?: $share->platforms()->keys());

        $targets = $pending->links()->targets();

        if ($this->option('json')) {
            $this->line((string) json_encode($targets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach ($targets as $platform => $target) {
            $this->components->twoColumnDetail("<fg=cyan>{$platform}</>", '');
            $this->line('  '.$target);
        }

        $skipped = array_diff($pending->platformKeys(), array_keys($pending->links()->all()));
        if ($skipped !== []) {
            $this->newLine();
            $this->components->warn('Left out (the share lacks what they need): '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }
}
