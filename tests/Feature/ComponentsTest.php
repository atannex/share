<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Feature;

use Atannex\Share\Facades\Share;
use Atannex\Share\Tests\Fixtures\Article;
use Atannex\Share\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

final class ComponentsTest extends TestCase
{
    private function article(): Article
    {
        return Article::create(['title' => 'A "quoted" & <b>bold</b> title', 'slug' => 'quoted', 'excerpt' => 'Short summary.', 'cover_url' => 'https://cdn.test/c.jpg']);
    }

    #[Test]
    public function buttons(): void
    {
        $html = Blade::render('<x-share::buttons :for="$article" only="facebook,x,whatsapp,messenger,copy,native" variant="brand" sheet="sheet" class="my-row" />', ['article' => $this->article()]);

        $this->assertStringContainsString('class="share share--brand share--md my-row"', $html);
        $this->assertStringContainsString('data-share-platform="facebook"', $html);
        $this->assertStringContainsString('data-share-popup="620x680"', $html);
        $this->assertStringContainsString('aria-label="Share on X"', $html);
        $this->assertStringNotContainsString('title="Share on', $html, 'content blockers hide those');
        $this->assertMatchesRegularExpression('/data-share-platform="messenger"[^>]*data-share-beacon[^>]*data-share-mobile/', $html);
        $this->assertMatchesRegularExpression('/<button type="button" data-share-platform="native"[^>]*hidden/', $html);
        $this->assertStringContainsString('data-share-open="sheet"', $html);
        $this->assertStringContainsString('&quot;beacon&quot;', $html, 'the share settings ride along, escaped');
        $this->assertStringNotContainsString('data-share-popup', $this->between($html, 'data-share-platform="whatsapp"', '>'));
    }

    #[Test]
    public function sheet(): void
    {
        $html = Blade::render('<x-share::sheet :share="$share" id="story-share" />', [
            'share' => Share::for($this->article())->embed('<iframe src="x"></iframe>')->only('facebook', 'pinterest', 'copy', 'qr', 'embed', 'native'),
        ]);

        $this->assertStringContainsString('<dialog id="story-share"', $html);
        $this->assertStringContainsString('aria-labelledby="story-share-title"', $html);
        $this->assertStringContainsString('A &quot;quoted&quot; &amp; bold title', $html);
        $this->assertStringContainsString('data-share-platform="pinterest"', $html, 'it has an image');
        $this->assertStringContainsString('<svg', $this->between($html, 'share-sheet__qr-code', '</div>'));
        $this->assertStringContainsString('&lt;iframe src=&quot;x&quot;&gt;&lt;/iframe&gt;</textarea>', $html);
        $this->assertStringContainsString('value="http://localhost/stories/quoted?utm_source=copy&amp;utm_medium=copy&amp;utm_campaign=share"', $html);
        $this->assertStringNotContainsString('data-share-files', $html, 'no files, no file button');

        $withFile = Blade::render('<x-share::sheet :share="$share" />', ['share' => Share::for($this->article())->file('https://cdn.test/v.mp4', 'video/mp4', size: 1024)]);
        $this->assertMatchesRegularExpression('/<button type="button" hidden data-share-files data-share-platform="native"/', $withFile);
        $this->assertStringContainsString('&quot;files&quot;:[{&quot;url&quot;:&quot;https://cdn.test/v.mp4', $withFile);
    }

    #[Test]
    public function meta_tags(): void
    {
        $html = Blade::render('<x-share::meta :share="$share" />', [
            'share' => Share::for($this->article())->image('https://cdn.test/c.jpg', 'Cover', 1200, 630)->video('https://cdn.test/v.mp4', player: 'https://news.test/embed/1')->meta(['article:published_time' => '2026-10-25']),
        ]);

        foreach ([
            '<meta property="og:title" content="A &quot;quoted&quot; &amp; bold title">',
            '<meta property="og:description" content="Short summary.">',
            '<meta property="og:url" content="http://localhost/stories/quoted">',
            '<meta property="og:type" content="article">',
            '<meta property="og:site_name" content="Atannex">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:alt" content="Cover">',
            '<meta name="twitter:card" content="player">',
            '<meta name="twitter:site" content="@atannex">',
            '<meta name="twitter:player" content="https://news.test/embed/1">',
            '<meta property="og:video:type" content="video/mp4">',
            '<meta property="article:published_time" content="2026-10-25">',
        ] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
    }

    #[Test]
    public function scripts_and_styles_inline_once(): void
    {
        $html = Blade::render('<x-share::styles nonce="abc" /><x-share::scripts nonce="abc" /><x-share::scripts />');

        $this->assertSame(1, substr_count($html, '<script'));
        $this->assertStringContainsString('<script nonce="abc" data-share-messages=', $html);
        $this->assertStringContainsString('window.AtannexShare', $html);
        $this->assertStringContainsString('<style nonce="abc">', $html);
        $this->assertStringContainsString('--share-size', $html);
    }

    #[Test]
    public function a_pending_share_renders_itself(): void
    {
        $html = (string) Share::url('https://a.test', 'A')->only('x', 'copy')->toHtml();

        $this->assertStringContainsString('data-share-platform="x"', $html);
        $this->assertStringContainsString('data-share-action="copy"', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://a.test">', (string) Share::url('https://a.test')->metaTags());
    }

    #[Test]
    public function labels_translate(): void
    {
        app()->setLocale('fr');

        $html = Blade::render('<x-share::buttons for="https://a.test" only="x,copy" />');

        $this->assertStringContainsString('aria-label="Partager sur X"', $html);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "{$start} is in the page");

        return substr($html, $from, strpos($html, $end, $from + strlen($start)) - $from);
    }
}
