<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Unit;

use Atannex\Share\Links\UrlComposer;
use Atannex\Share\Platforms\Platform;
use Atannex\Share\Platforms\PlatformRegistry;
use Atannex\Share\ShareData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlComposerTest extends TestCase
{
    private PlatformRegistry $platforms;

    protected function setUp(): void
    {
        $this->platforms = new PlatformRegistry(PlatformRegistry::catalog());
    }

    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    #[Test]
    public function values_with_reserved_characters_arrive_intact(): void
    {
        $share = new ShareData(url: 'https://news.test/a?b=1&c=2#top', title: 'Rock & "roll" #1 — café');
        $url = (new UrlComposer)->compose($share, $this->platforms->get('reddit'));

        $this->assertStringStartsWith('https://www.reddit.com/submit?', $url);
        $this->assertStringContainsString('%20', $url, 'RFC 3986: spaces are %20, never +');
        $this->assertSame(['url' => 'https://news.test/a?b=1&c=2#top', 'title' => 'Rock & "roll" #1 — café'], $this->query($url));
    }

    #[Test]
    public function empty_parameters_are_left_out(): void
    {
        $url = (new UrlComposer)->compose(new ShareData(url: 'https://news.test/a', title: 'T'), $this->platforms->get('x'));

        $this->assertSame(['text' => 'T', 'url' => 'https://news.test/a'], $this->query($url));
    }

    #[Test]
    public function the_post_is_shortened_to_the_platform_limit(): void
    {
        $share = new ShareData(url: 'https://news.test/'.str_repeat('long-', 40), title: str_repeat('word ', 100), via: 'atannex', hashtags: ['Lebialem', 'Cameroon']);
        $query = $this->query((new UrlComposer)->compose($share, $this->platforms->get('x')));

        // X counts any link as 23 characters.
        $length = mb_strlen($query['text']) + 24 + mb_strlen(' via @atannex') + mb_strlen(' #Lebialem #Cameroon');
        $this->assertLessThanOrEqual(280, $length);
        $this->assertGreaterThan(200, mb_strlen($query['text']));
        $this->assertStringEndsWith('…', $query['text']);
        $this->assertSame('Lebialem,Cameroon', $query['hashtags']);
        $this->assertSame('atannex', $query['via']);
    }

    #[Test]
    public function bluesky_counts_the_whole_link(): void
    {
        $share = new ShareData(url: 'https://news.test/'.str_repeat('x', 100), title: str_repeat('word ', 100));
        $query = $this->query((new UrlComposer)->compose($share, $this->platforms->get('bluesky')));

        $this->assertLessThanOrEqual(300, mb_strlen($query['text']));
        $this->assertStringEndsWith("\n\n".$share->url, $query['text']);
    }

    #[Test]
    public function campaign_tags_keep_existing_parameters_and_the_fragment(): void
    {
        $composer = new UrlComposer(['utm_source' => '{platform}', 'utm_medium' => '{medium}', 'utm_campaign' => 'share']);

        $this->assertSame(
            'https://news.test/a?utm_source=custom&utm_medium=messaging&utm_campaign=share#comments',
            $composer->tag('https://news.test/a?utm_source=custom#comments', $this->platforms->get('whatsapp')),
        );
        $this->assertSame('mailto:x@y.z', $composer->tag('mailto:x@y.z', 'email'));

        $query = $this->query($composer->compose(new ShareData(url: 'https://news.test/a'), $this->platforms->get('facebook')));
        $this->assertSame('https://news.test/a?utm_source=facebook&utm_medium=social&utm_campaign=share', $query['u']);
    }

    #[Test]
    public function the_url_template_takes_the_platforms_options(): void
    {
        $this->platforms->define('mastodon', ['instance' => 'mas.to']);
        $url = (new UrlComposer)->compose(new ShareData(url: 'https://news.test/a', title: 'Hello'), $this->platforms->get('mastodon'));

        $this->assertSame('https://mas.to/share?text=Hello%0A%0Ahttps%3A%2F%2Fnews.test%2Fa', $url);
    }

    #[Test]
    public function schemes_and_separators(): void
    {
        $share = new ShareData(url: 'https://news.test/a', title: 'Hi there', text: 'Summary');

        $this->assertSame('sms:?&body=Hi%20there%0A%0Ahttps%3A%2F%2Fnews.test%2Fa', (new UrlComposer)->compose($share, $this->platforms->get('sms')));
        $this->assertSame('mailto:?subject=Hi%20there&body=Summary%0A%0Ahttps%3A%2F%2Fnews.test%2Fa', (new UrlComposer)->compose($share, $this->platforms->get('email')));
        $this->assertNull((new UrlComposer)->compose($share, $this->platforms->get('copy')));
    }

    #[Test]
    public function a_build_closure_replaces_the_template(): void
    {
        $platform = Platform::fromArray('intranet', ['label' => 'Intranet', 'build' => fn (ShareData $share) => 'https://intra.test/s/'.md5((string) $share->url)]);

        $this->assertSame('https://intra.test/s/'.md5('https://news.test/a'), (new UrlComposer)->compose(new ShareData(url: 'https://news.test/a'), $platform));
    }

    #[Test]
    public function every_built_in_platform_builds_a_well_formed_address(): void
    {
        $share = new ShareData(url: 'https://news.test/a', title: 'Title', text: 'Text', image: 'https://news.test/i.jpg', hashtags: ['One'], via: 'atannex', embed: '<iframe></iframe>');
        $composer = new UrlComposer;

        foreach ($this->platforms->all() as $key => $platform) {
            $this->assertTrue($platform->supports($share), "{$key} supports a full share");
            $url = $composer->compose($share, $platform);

            if ($platform->isClient()) {
                $this->assertNull($url, $key);

                continue;
            }

            $this->assertMatchesRegularExpression('~^[a-z][a-z0-9+.-]*:~', (string) $url, $key);
            $this->assertStringNotContainsString('{', (string) $url, "{$key} has no unfilled placeholder");
            $platform->isWeb() && $this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL), "{$key}: {$url}");
        }
    }
}
