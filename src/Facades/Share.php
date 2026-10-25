<?php

declare(strict_types=1);

namespace Atannex\Share\Facades;

use Atannex\Share\ShareManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atannex\Share\PendingShare for(mixed $subject = null)
 * @method static \Atannex\Share\PendingShare page()
 * @method static \Atannex\Share\PendingShare url(string $url, ?string $title = null)
 * @method static \Atannex\Share\PendingShare text(string $message)
 * @method static bool record(string $platform, ?string $url = null, \Atannex\Share\Subject|\Illuminate\Database\Eloquent\Model|null $subject = null, ?\Illuminate\Http\Request $request = null)
 * @method static int count(\Illuminate\Database\Eloquent\Model|\Atannex\Share\Subject|string|null $subject = null, string|array|null $platform = null, ?\DateTimeInterface $since = null)
 * @method static array<string, int> breakdown(\Illuminate\Database\Eloquent\Model|\Atannex\Share\Subject|string|null $subject = null, ?\DateTimeInterface $since = null)
 * @method static ShareManager extend(string $key, array|\Atannex\Share\Platforms\Platform $definition)
 * @method static ShareManager resolveUsing(string $class, \Closure $resolver)
 * @method static ShareManager recordUsing(\Atannex\Share\Contracts\Recorder|\Closure $recorder)
 * @method static \Atannex\Share\Platforms\Platform platform(string $key)
 * @method static \Atannex\Share\Platforms\PlatformRegistry platforms()
 * @method static ?string qr(string $text, int $size = 220)
 * @method static \Atannex\Share\Testing\FakeRecorder fake()
 * @method static void assertShared(string $platform, \Illuminate\Database\Eloquent\Model|\Atannex\Share\Subject|string|null $subject = null, ?int $times = null)
 * @method static void assertNotShared(string $platform, \Illuminate\Database\Eloquent\Model|\Atannex\Share\Subject|string|null $subject = null)
 * @method static void assertNothingShared()
 *
 * @see ShareManager
 */
final class Share extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShareManager::class;
    }
}
