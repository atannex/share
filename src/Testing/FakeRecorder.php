<?php

declare(strict_types=1);

namespace Atannex\Share\Testing;

use Atannex\Share\Contracts\Recorder;
use Atannex\Share\Events\Shared;
use Atannex\Share\Subject;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * Share::fake(): keeps counted shares in memory for assertions.
 */
final class FakeRecorder implements Recorder
{
    /** @var list<Shared> */
    private array $shares = [];

    public function record(Shared $share): void
    {
        $this->shares[] = $share;
    }

    /**
     * @return list<Shared>
     */
    public function shares(?string $platform = null, Model|Subject|string|null $subject = null): array
    {
        return array_values(array_filter($this->shares, function (Shared $share) use ($platform, $subject) {
            if ($platform !== null && $share->platform !== $platform) {
                return false;
            }

            return match (true) {
                $subject === null => true,
                is_string($subject) => $share->url === $subject,
                $subject instanceof Model => (bool) $share->subject?->is($subject),
                default => $share->subject?->reference() === $subject->reference(),
            };
        }));
    }

    public function assertShared(string $platform, Model|Subject|string|null $subject = null, ?int $times = null): void
    {
        $count = count($this->shares($platform, $subject));

        if ($times === null) {
            Assert::assertGreaterThan(0, $count, "Nothing was shared on [{$platform}].");
        } else {
            Assert::assertSame($times, $count, "Expected {$times} share(s) on [{$platform}], got {$count}.");
        }
    }

    public function assertNotShared(string $platform, Model|Subject|string|null $subject = null): void
    {
        Assert::assertCount(0, $this->shares($platform, $subject), "Unexpected share on [{$platform}].");
    }

    public function assertNothingShared(): void
    {
        Assert::assertCount(0, $this->shares, 'Shares were recorded.');
    }
}
