<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\PendingProcess;

class ForeverProcessFactory extends ProcessFactory
{
    public function newPendingProcess(): PendingProcess
    {
        return parent::newPendingProcess()->forever();
    }
}
