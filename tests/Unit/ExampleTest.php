<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_that_true_is_true(): void
    {
        $value = new \stdClass;

        $this->assertInstanceOf(\stdClass::class, $value);
    }
}
