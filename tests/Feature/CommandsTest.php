<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Feature;

use Atannex\Share\Inspection\PreviewInspector;
use Atannex\Share\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class CommandsTest extends TestCase
{
    #[Test]
    public function platforms(): void
    {
        $this->artisan('share:platforms')
            ->expectsOutputToContain('bluesky')
            ->expectsOutputToContain('offered by default')
            ->assertSuccessful();
    }

    #[Test]
    public function links(): void
    {
        $this->artisan('share:links', ['url' => 'https://a.test/x', '--title' => 'Hello', '--platform' => ['x', 'pinterest']])
            ->expectsOutputToContain('https://x.com/intent/post?text=Hello&url=https%3A%2F%2Fa.test%2Fx')
            ->expectsOutputToContain('Left out (the share lacks what they need): pinterest')
            ->assertSuccessful();
    }

    #[Test]
    public function inspect_a_good_page(): void
    {
        Http::fake([
            'page.test/i.png' => Http::response($this->png(1200, 630), 200, ['Content-Type' => 'image/png']),
            'page.test/*' => Http::response($this->page([
                'og:title' => 'A story', 'og:description' => 'What happened.', 'og:url' => 'https://page.test/story',
                'og:type' => 'article', 'og:image' => 'https://page.test/i.png', 'twitter:card' => 'summary_large_image',
            ], canonical: 'https://page.test/story')),
        ]);

        $report = app(PreviewInspector::class)->inspect('https://page.test/story');

        $this->assertSame('large image', $report['card']);
        $this->assertSame([1200, 630], [$report['image']['width'], $report['image']['height']]);
        $this->assertSame([], $report['issues']);

        $this->artisan('share:inspect', ['url' => 'https://page.test/story'])->expectsOutputToContain('Ready to share')->assertSuccessful();
    }

    #[Test]
    public function inspect_a_bare_page(): void
    {
        Http::fake([
            'bare.test/*' => Http::response($this->page(['og:image' => '/relative.png'], title: 'Only a title')),
        ]);

        $report = app(PreviewInspector::class)->inspect('https://bare.test/x');
        $messages = implode("\n", array_column($report['issues'], 'message'));

        $this->assertSame('Only a title', $report['title']);
        $this->assertStringContainsString('No og:title; platforms fall back to <title>', $messages);
        $this->assertStringContainsString('not an absolute address', $messages);
        $this->assertStringContainsString('No twitter:card', $messages);

        $this->artisan('share:inspect', ['url' => 'https://bare.test/x'])->assertFailed();
    }

    #[Test]
    public function inspect_a_small_image(): void
    {
        Http::fake([
            'small.test/i.png' => Http::response($this->png(150, 150), 200, ['Content-Type' => 'image/png']),
            'small.test/*' => Http::response($this->page(['og:title' => 'T', 'og:image' => 'https://small.test/i.png'])),
        ]);

        $messages = implode("\n", array_column(app(PreviewInspector::class)->inspect('https://small.test/x')['issues'], 'message'));

        $this->assertStringContainsString('150×150; Facebook needs at least 200×200', $messages);
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function page(array $tags, ?string $title = null, ?string $canonical = null): string
    {
        $meta = '';
        foreach ($tags as $key => $value) {
            $attribute = str_starts_with($key, 'twitter:') ? 'name' : 'property';
            $meta .= "<meta {$attribute}=\"{$key}\" content=\"{$value}\">";
        }

        return '<!doctype html><html><head><title>'.($title ?? 'Page').'</title>'.$meta.($canonical ? "<link rel=\"canonical\" href=\"{$canonical}\">" : '').'</head><body>Hi</body></html>';
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
