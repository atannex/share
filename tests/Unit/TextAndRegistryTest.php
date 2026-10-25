<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Unit;

use Atannex\Share\Exceptions\UnknownPlatform;
use Atannex\Share\Platforms\PlatformRegistry;
use Atannex\Share\ShareData;
use Atannex\Share\Support\Icons;
use Atannex\Share\Support\Text;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextAndRegistryTest extends TestCase
{
    #[Test]
    public function text_is_cut_at_a_word_within_the_limit(): void
    {
        $this->assertSame('Short', Text::limit('Short', 10));
        $this->assertSame('The quick brown…', Text::limit('The quick brown fox jumps', 18));
        $this->assertLessThanOrEqual(18, mb_strlen(Text::limit('Thequickbrownfoxjumps', 18)));
        $this->assertSame('Ünïcödé…', Text::limit('Ünïcödé text here', 9));
        $this->assertSame('', Text::limit('anything', 0));
    }

    #[Test]
    public function aliases_and_custom_platforms(): void
    {
        $registry = new PlatformRegistry(PlatformRegistry::catalog(), [
            'x' => ['label' => 'Twitter'],
            'intranet' => ['label' => 'Intranet', 'url' => 'https://intra.test/share', 'query' => ['u' => '{url}']],
        ]);

        $this->assertSame('x', $registry->get('twitter')->key);
        $this->assertSame('Twitter', $registry->get('x')->label);
        $this->assertSame('https://x.com/intent/post', $registry->get('x')->url, 'custom values merge into the built-in one');
        $this->assertSame('copy', $registry->key('link'));
        $this->assertTrue($registry->has('intranet'));

        $this->expectException(UnknownPlatform::class);
        $registry->get('myspace');
    }

    #[Test]
    public function requirements(): void
    {
        $registry = new PlatformRegistry(PlatformRegistry::catalog());
        $link = new ShareData(url: 'https://news.test/a');
        $text = new ShareData(message: 'Just words');

        $this->assertFalse($registry->get('pinterest')->supports($link));
        $this->assertTrue($registry->get('pinterest')->supports($link->with(['image' => 'https://news.test/i.jpg'])));
        $this->assertFalse($registry->get('facebook')->supports($text));
        $this->assertTrue($registry->get('whatsapp')->supports($text));
        $this->assertTrue($registry->get('copy')->supports($text));
        $this->assertFalse($registry->get('embed')->supports($link));
    }

    #[Test]
    public function every_platform_has_a_mark(): void
    {
        foreach ((new PlatformRegistry(PlatformRegistry::catalog()))->all() as $platform) {
            $this->assertTrue(Icons::has((string) $platform->icon), "icon for {$platform->key}");
        }

        $this->assertStringContainsString('<text', Icons::render('nope', 'N'));
        $this->assertSame('<i class="share-icon fab fa-x" aria-hidden="true"></i>', Icons::render('fab fa-x'));
    }
}
