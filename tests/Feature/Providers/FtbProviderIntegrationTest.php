<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Services\Providers\FtbProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FtbProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_source_with_real_manifest(): void
    {
        $packId = getenv('TEST_FTB_PACK_ID');
        $versionId = getenv('TEST_FTB_VERSION_ID');

        if (! is_string($packId) || $packId === '' || ! is_string($versionId) || $versionId === '') {
            $this->markTestSkipped('FTB integration test skipped: missing env TEST_FTB_PACK_ID/TEST_FTB_VERSION_ID');
        }

        $provider = new FtbProvider;
        $src = $provider->fetchSource($packId, $versionId);
        $this->assertSame('dir', $src['type']);
        $this->assertDirectoryExists($src['path']);
    }
}
