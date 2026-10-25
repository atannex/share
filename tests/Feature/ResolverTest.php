<?php

declare(strict_types=1);

namespace Atannex\Share\Tests\Feature;

use Atannex\Share\Exceptions\CannotResolveShare;
use Atannex\Share\Facades\Share;
use Atannex\Share\ShareData;
use Atannex\Share\Tests\Fixtures\Article;
use Atannex\Share\Tests\Fixtures\Event;
use Atannex\Share\Tests\Fixtures\Person;
use Atannex\Share\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;

final class ResolverTest extends TestCase
{
    #[Test]
    public function a_model_mapped_with_share_as(): void
    {
        $article = Article::create(['title' => 'Land <b>use</b> &amp; you', 'slug' => 'land-use', 'excerpt' => '<p>Why it matters.</p>', 'cover_url' => '/img/cover.jpg']);
        $article->tags()->createMany([['name' => 'land use'], ['name' => 'Lebialem'], ['name' => 'Extra']]);

        $share = Share::for($article)->data();

        $this->assertSame('http://localhost/stories/land-use', $share->url);
        $this->assertSame('Land use & you', $share->title);
        $this->assertSame('Why it matters.', $share->text);
        $this->assertSame('http://localhost/img/cover.jpg', $share->image);
        $this->assertSame(['LandUse', 'Lebialem'], $share->hashtags, 'two at most (config share.hashtags)');
        $this->assertSame('article', $share->type);
        $this->assertTrue($share->subject->is($article));
        $this->assertSame('atannex', $share->via, 'config defaults fill the gaps');
        $this->assertSame('Atannex', $share->siteName);
    }

    #[Test]
    public function an_unmapped_model_is_read_from_common_names(): void
    {
        $person = Person::create(['name' => 'Fon of Fontem', 'description' => 'A ruler.', 'url' => 'http://localhost/rulers/fontem']);

        $share = $person->share()->data();

        $this->assertSame('http://localhost/rulers/fontem', $share->url);
        $this->assertSame('Fon of Fontem', $share->title);
        $this->assertSame('A ruler.', $share->text);
    }

    #[Test]
    public function a_model_without_an_address_explains_what_to_do(): void
    {
        $this->expectException(CannotResolveShare::class);
        $this->expectExceptionMessage('#[Shareable(route: ...)]');

        Share::for(Person::create(['name' => 'Nobody']));
    }

    #[Test]
    public function anything_else(): void
    {
        $this->assertSame('https://example.com/x', Share::for('https://example.com/x')->data()->url);
        $this->assertSame('http://localhost/about', Share::for('/about')->data()->url);

        $text = Share::for('Just some words to pass on')->data();
        $this->assertNull($text->url);
        $this->assertSame('Just some words to pass on', $text->message);

        $event = Share::for(new Event('Ngondo festival', 'ngondo'))->data();
        $this->assertSame('http://localhost/events/ngondo', $event->url);
        $this->assertSame(['LiveEvents', 'Bamenda'], $event->hashtags);

        $array = Share::for(['link' => 'https://a.test', 'name' => 'A', 'summary' => 'B'])->data();
        $this->assertSame(['https://a.test', 'A', 'B'], [$array->url, $array->title, $array->text]);

        $this->assertSame('C', Share::for(fn () => ['url' => 'https://c.test', 'title' => 'C'])->data()->title);
        $this->assertSame('https://d.test', Share::for(new ShareData(url: 'https://d.test'))->data()->url);
    }

    #[Test]
    public function the_current_page(): void
    {
        $this->get('/stories/today');

        $this->app['url']->setRequest(request()->create('http://localhost/stories/today?ref=1'));
        $this->assertSame('http://localhost/stories/today', Share::page()->data()->url);
    }

    #[Test]
    public function a_resolver_for_a_class_you_dont_own(): void
    {
        Share::resolveUsing(\ArrayObject::class, fn (\ArrayObject $o) => ['url' => $o['href'], 'title' => 'From a resolver']);

        $this->assertSame('From a resolver', Share::for(new \ArrayObject(['href' => 'https://e.test']))->data()->title);
    }

    #[Test]
    public function fluent_changes_are_cleaned_up(): void
    {
        $share = Share::url('https://a.test', 'Title')
            ->message('<em>Read</em> this')
            ->hashtags('#One, two words', ['Three'])
            ->via('@someone')
            ->image('/i.png', 'Alt', 1200, 630)
            ->file('/clip.mp4', 'video/mp4', size: 1000)
            ->data();

        $this->assertSame('Read this', $share->message);
        $this->assertSame(['One', 'TwoWords'], $share->hashtags);
        $this->assertSame('someone', $share->via);
        $this->assertSame(['http://localhost/i.png', 'Alt', 1200, 630], [$share->image, $share->imageAlt, $share->imageWidth, $share->imageHeight]);
        $this->assertSame([['url' => 'http://localhost/clip.mp4', 'mime' => 'video/mp4', 'name' => 'clip.mp4', 'size' => 1000]], $share->files);
    }

    #[Test]
    public function the_helper_and_json(): void
    {
        $json = json_decode(json_encode(share('https://a.test')->only('x', 'copy')), true);

        $this->assertSame(['x', 'copy'], array_keys($json['links']));
        $this->assertSame('https://a.test', $json['share']['url']);
        $this->assertArrayHasKey('beacon', $json['client']);
        $this->assertInstanceOf(Model::class, new Article);
    }
}
