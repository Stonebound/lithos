<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Utils\ForeverProcessFactory;
use Tests\TestCase;

class ForeverProcessFactoryTest extends TestCase
{
    public function test_new_pending_process_runs_forever(): void
    {
        $factory = new ForeverProcessFactory;

        $this->assertNull($factory->newPendingProcess()->timeout);
    }
}
