<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
use Mockery\Expectation;
use Mockery\MockInterface;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function expectMock(MockInterface $mock, string $method): Expectation
    {
        $mock->shouldReceive($method);
        $director = $mock->mockery_getExpectationsFor($method);
        $expectations = $director?->getExpectations() ?? [];

        $expectation = $expectations[array_key_last($expectations)] ?? null;

        if (! $expectation instanceof Expectation) {
            throw new RuntimeException(sprintf('Unable to create a typed Mockery expectation for [%s].', $method));
        }

        return $expectation;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $pendingCommand = $this->artisan($command, $parameters);

        if (is_int($pendingCommand)) {
            throw new RuntimeException(sprintf('Artisan command [%s] returned an unexpected integer result.', $command));
        }

        return $pendingCommand;
    }
}
