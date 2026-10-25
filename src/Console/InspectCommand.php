<?php

declare(strict_types=1);

namespace Atannex\Share\Console;

use Atannex\Share\Inspection\PreviewInspector;
use Illuminate\Console\Command;

final class InspectCommand extends Command
{
    protected $signature = 'share:inspect
        {url : The page to check}
        {--no-image : Don\'t download the image}
        {--json : Print the report as JSON}';

    protected $description = 'Check how a page will look when it is shared (Open Graph and X card tags, the image)';

    public function handle(PreviewInspector $inspector): int
    {
        $report = $inspector->inspect((string) $this->argument('url'), ! $this->option('no-image'));
        $errors = array_filter($report['issues'], fn ($issue) => $issue['level'] === 'error');

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>Page</>', $report['url'].($report['status'] ? " <fg=gray>(HTTP {$report['status']})</>" : ''));
        $this->components->twoColumnDetail('Title', $report['title'] ?? '<fg=red>none</>');
        $this->components->twoColumnDetail('Description', $report['description'] ? mb_strimwidth($report['description'], 0, 90, '…') : '<fg=yellow>none</>');

        $image = $report['image'];
        $this->components->twoColumnDetail('Image', $image === null ? '<fg=yellow>none</>' : $image['url']);
        if ($image && $image['width']) {
            $this->components->twoColumnDetail('', sprintf('%d×%d · %s · %s KB', $image['width'], $image['height'], $image['type'] ?? '?', number_format(($image['bytes'] ?? 0) / 1024)));
        }
        $this->components->twoColumnDetail('Card', $report['card']);
        $this->newLine();

        foreach ($report['issues'] as $issue) {
            match ($issue['level']) {
                'error' => $this->components->error($issue['message']),
                'warning' => $this->components->warn($issue['message']),
                default => $this->components->info($issue['message']),
            };
        }

        if ($report['issues'] === []) {
            $this->components->info('Ready to share: every platform has what it needs.');
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
