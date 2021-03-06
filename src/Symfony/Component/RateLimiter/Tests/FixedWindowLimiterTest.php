<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Tests\Resources\DummyWindow;

/**
 * @group time-sensitive
 */
class FixedWindowLimiterTest extends TestCase
{
    private $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
    }

    public function testConsume()
    {
        $limiter = $this->createLimiter();

        // fill 9 tokens in 45 seconds
        for ($i = 0; $i < 9; ++$i) {
            $limiter->consume();
            sleep(5);
        }

        $limit = $limiter->consume();
        $this->assertTrue($limit->isAccepted());
        $limit = $limiter->consume();
        $this->assertFalse($limit->isAccepted());
    }

    public function testConsumeOutsideInterval()
    {
        $limiter = $this->createLimiter();

        // start window...
        $limiter->consume();
        // ...add a max burst at the end of the window...
        sleep(55);
        $limiter->consume(9);
        // ...try bursting again at the start of the next window
        sleep(10);
        $limit = $limiter->consume(10);
        $this->assertEquals(0, $limit->getRemainingTokens());
        $this->assertTrue($limit->isAccepted());
    }

    public function testWrongWindowFromCache()
    {
        $this->storage->save(new DummyWindow());
        $limiter = $this->createLimiter();
        $limit = $limiter->consume();
        $this->assertTrue($limit->isAccepted());
        $this->assertEquals(9, $limit->getRemainingTokens());
    }

    private function createLimiter(): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test', 10, new \DateInterval('PT1M'), $this->storage);
    }
}
