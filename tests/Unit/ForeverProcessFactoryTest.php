<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Utils\ForeverProcessFactory;
use Illuminate\Concurrency\ConcurrencyManager;
use Illuminate\Concurrency\ProcessDriver;
use ReflectionClass;
use Tests\TestCase;

class ForeverProcessFactoryTest extends TestCase
{
    public function test_new_pending_process_runs_forever(): void
    {
        $factory = new ForeverProcessFactory;

        $this->assertNull($factory->newPendingProcess()->timeout);
    }

    public function test_forever_process_driver_uses_custom_factory(): void
    {
        /** @var ConcurrencyManager $manager */
        $manager = $this->app->make(ConcurrencyManager::class);
        /** @var ProcessDriver $driver */
        $driver = $manager->driver('forever-process');

        $reflection = new ReflectionClass($driver);
        $property = $reflection->getProperty('processFactory');
        $property->setAccessible(true);

        $this->assertInstanceOf(ForeverProcessFactory::class, $property->getValue($driver));
    }
}
