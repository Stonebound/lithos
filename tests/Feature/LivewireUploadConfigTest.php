<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\PhpUploadLimit;
use Tests\TestCase;

class LivewireUploadConfigTest extends TestCase
{
    public function test_livewire_upload_rules_are_synced_to_php_limits_at_runtime(): void
    {
        $this->assertSame([
            'required',
            'file',
            'max:'.PhpUploadLimit::maxUploadKilobytes(),
        ], config('livewire.temporary_file_upload.rules'));
    }
}
