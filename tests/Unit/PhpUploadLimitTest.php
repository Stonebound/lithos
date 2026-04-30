<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PhpUploadLimit;
use PHPUnit\Framework\TestCase;

class PhpUploadLimitTest extends TestCase
{
    public function test_it_parses_php_size_suffixes_to_bytes(): void
    {
        $this->assertSame(512, PhpUploadLimit::parseToBytes('512'));
        $this->assertSame(2048, PhpUploadLimit::parseToBytes('2K'));
        $this->assertSame(3145728, PhpUploadLimit::parseToBytes('3M'));
        $this->assertSame(1073741824, PhpUploadLimit::parseToBytes('1G'));
    }

    public function test_it_uses_the_smaller_php_limit_for_upload_kilobytes(): void
    {
        $this->assertSame(2048, PhpUploadLimit::maxUploadKilobytes('2M', '8M'));
        $this->assertSame(1024, PhpUploadLimit::maxUploadKilobytes('8M', '1M'));
    }

    public function test_it_formats_the_effective_limit_for_display(): void
    {
        $this->assertSame('2 MB', PhpUploadLimit::humanReadableMaxUpload('2M', '8M'));
        $this->assertSame('512 KB', PhpUploadLimit::humanReadableMaxUpload('512K', '8M'));
    }
}
