<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Feature;

use Atannex\Share\Events\Shared;
use Atannex\Share\Facades\Share;
use Atannex\Share\Models\ShareCount;
use Atannex\Share\Tests\Fixtures\Article;
use Atannex\Share\Tests\TestCase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

final class TrackingTest extends TestCase
{
    private function article(): Article
    {
        return Article::create(['title' => 'Story', 'slug' => 'story']);
    }

    #[Test]
    public function web_platforms_go_through_a_signed_counted_redirect(): void
    {
        Event::fake([Shared::class]);
        $article = $this->article();
        $link = $article->share()->link('facebook');

        $this->assertStringStartsWith('http://localhost/share/facebook?', $link->href);
        $this->assertStringStartsWith('https://www.facebook.com/sharer/sharer.php?u=', $link->target);

        $this->get($link->href, $this->browser())->assertRedirect($link->target);

        $this->assertSame(1, $article->shareCount());
        $this->assertSame(1, $article->shareCount('facebook'));
        $this->assertSame(['facebook' => 1], $article->shareBreakdown());
        Event::assertDispatched(Shared::class, fn (Shared $e) => $e->platform === 'facebook' && $e->model()?->is($article) && $e->url === 'http://localhost/stories/story');
    }

    #[Test]
    public function a_tampered_link_goes_nowhere(): void
    {
        $href = $this->article()->share()->href('x');

        $this->get(str_replace('x.com', 'evil.test', $href), $this->browser())->assertNotFound();
        $this->get('/share/x?to='.urlencode('https://evil.test'), $this->browser())->assertNotFound();
        $this->assertSame(0, ShareCount::count());
    }

    #[Test]
    public function bots_prefetches_and_repeats_are_not_counted(): void
    {
        $article = $this->article();
        $href = $article->share()->href('linkedin');

        $this->get($href, ['User-Agent' => 'facebookexternalhit/1.1'])->assertRedirect();
        $this->get($href, $this->browser() + ['Sec-Purpose' => 'prefetch'])->assertRedirect();
        $this->get($href, ['User-Agent' => ''])->assertRedirect();
        $this->assertSame(0, $article->shareCount());

        $this->get($href, $this->browser());
        $this->get($href, $this->browser());
        $this->assertSame(1, $article->shareCount(), 'the same reader within the dedupe window counts once');
    }

    #[Test]
    public function counts_add_up_in_one_row_per_day(): void
    {
        // Recorded in code (no request): never filtered or deduplicated.
        $article = $this->article();

        foreach (['x', 'x', 'whatsapp'] as $platform) {
            Share::record($platform, 'http://localhost/stories/story', $article);
        }

        $this->assertSame(2, ShareCount::count());
        $this->assertSame(3, $article->shareCount());
        $this->assertSame(['x' => 2, 'whatsapp' => 1], $article->shareBreakdown());
        $this->assertSame(2, Share::count('http://localhost/stories/story', 'twitter'), 'aliases count as their platform');
        $this->assertSame(3, (int) Article::withShareCount()->first()->shares_count);
    }

    #[Test]
    public function browser_shares_report_by_beacon(): void
    {
        $article = $this->article();
        $client = $article->share()->client();

        $this->post($client['beacon'], ['platform' => 'copy'], $this->browser())->assertNoContent();
        $this->post($client['beacon'], ['platform' => 'email'], $this->browser())->assertNoContent();
        $this->post($client['beacon'], ['platform' => 'facebook'], $this->browser())->assertStatus(422);
        $this->post('/share/beacon', ['platform' => 'copy'], $this->browser())->assertForbidden();

        $this->assertSame(['copy' => 1, 'email' => 1], $article->shareBreakdown());
    }

    #[Test]
    public function the_beacon_needs_no_csrf_token(): void
    {
        // Pages are often served from a cache with a stale token; the beacon is signed instead.
        $excluded = app('router')->getRoutes()->getByName('share.beacon')->excludedMiddleware();

        $this->assertContains(ValidateCsrfToken::class, $excluded);
    }

    #[Test]
    public function without_tracking_links_go_straight_to_the_platform(): void
    {
        $link = $this->article()->share()->withoutTracking()->link('x');

        $this->assertSame($link->target, $link->href);
        $this->assertArrayNotHasKey('beacon', $this->article()->share()->withoutTracking()->client());
    }

    #[Test]
    public function a_custom_recorder(): void
    {
        $seen = [];
        Share::recordUsing(function (Shared $share) use (&$seen) {
            $seen[] = $share->platform;
        });

        $this->get($this->article()->share()->href('reddit'), $this->browser());

        $this->assertSame(['reddit'], $seen);
        $this->assertSame(0, ShareCount::count());
    }

    #[Test]
    public function the_fake(): void
    {
        Share::fake();
        $article = $this->article();

        $this->get($article->share()->href('telegram'), $this->browser());

        Share::assertShared('telegram', $article);
        Share::assertShared('telegram', times: 1);
        Share::assertNotShared('x');
        $this->assertSame(0, ShareCount::count());
    }
}
