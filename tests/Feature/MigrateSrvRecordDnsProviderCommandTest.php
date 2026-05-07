<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SrvRecord;
use App\Services\Dns\SrvDnsProvider;
use App\Services\Dns\SrvDnsProviderResolver;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrateSrvRecordDnsProviderCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function it_adopts_matching_records_on_the_active_provider(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'survival',
            'port' => 25565,
            'dns_provider' => 'bunny',
            'record_ids' => [1, 2],
        ]);

        /** @var SrvDnsProvider&MockInterface $provider */
        $provider = Mockery::mock(SrvDnsProvider::class);
        $this->expectMock($provider, 'findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);
        $provider->shouldNotReceive('createRecords');

        /** @var SrvDnsProviderResolver&MockInterface $resolver */
        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $this->expectMock($resolver, 'isConfigured')->once()->andReturn(true);
        $this->expectMock($resolver, 'providerName')->once()->andReturn('hetzner');
        $this->expectMock($resolver, 'resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisanCommand('srv-records:migrate-provider')
            ->assertExitCode(Command::SUCCESS);

        $srvRecord->refresh();

        $this->assertSame('hetzner', $srvRecord->dns_provider);
        $this->assertSame(['survival/SRV'], $srvRecord->record_ids);
    }

    #[Test]
    public function it_creates_records_when_no_match_exists_on_the_active_provider(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'survival',
            'port' => 25565,
            'dns_provider' => 'bunny',
            'record_ids' => [1, 2],
        ]);

        /** @var SrvDnsProvider&MockInterface $provider */
        $provider = Mockery::mock(SrvDnsProvider::class);
        $this->expectMock($provider, 'findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn([]);
        $this->expectMock($provider, 'createRecords')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);

        /** @var SrvDnsProviderResolver&MockInterface $resolver */
        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $this->expectMock($resolver, 'isConfigured')->once()->andReturn(true);
        $this->expectMock($resolver, 'providerName')->once()->andReturn('hetzner');
        $this->expectMock($resolver, 'resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisanCommand('srv-records:migrate-provider')
            ->assertExitCode(Command::SUCCESS);

        $srvRecord->refresh();

        $this->assertSame('hetzner', $srvRecord->dns_provider);
        $this->assertSame(['survival/SRV'], $srvRecord->record_ids);
    }

    #[Test]
    public function it_supports_dry_run_without_persisting_changes(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'survival',
            'port' => 25565,
            'dns_provider' => 'bunny',
            'record_ids' => [1, 2],
        ]);

        /** @var SrvDnsProvider&MockInterface $provider */
        $provider = Mockery::mock(SrvDnsProvider::class);
        $this->expectMock($provider, 'findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);
        $provider->shouldNotReceive('createRecords');

        /** @var SrvDnsProviderResolver&MockInterface $resolver */
        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $this->expectMock($resolver, 'isConfigured')->once()->andReturn(true);
        $this->expectMock($resolver, 'providerName')->once()->andReturn('hetzner');
        $this->expectMock($resolver, 'resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisanCommand('srv-records:migrate-provider --dry-run')
            ->assertExitCode(Command::SUCCESS);

        $srvRecord->refresh();

        $this->assertSame('bunny', $srvRecord->dns_provider);
        $this->assertSame([1, 2], $srvRecord->record_ids);
    }
}
