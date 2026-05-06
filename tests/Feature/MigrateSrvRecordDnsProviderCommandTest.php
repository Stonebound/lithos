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

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);
        $provider->shouldNotReceive('createRecords');

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('isConfigured')->once()->andReturn(true);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisan('srv-records:migrate-provider')
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

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn([]);
        $provider->shouldReceive('createRecords')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('isConfigured')->once()->andReturn(true);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisan('srv-records:migrate-provider')
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

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('findMatchingRecordIds')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['survival/SRV']);
        $provider->shouldNotReceive('createRecords');

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('isConfigured')->once()->andReturn(true);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->artisan('srv-records:migrate-provider --dry-run')
            ->assertExitCode(Command::SUCCESS);

        $srvRecord->refresh();

        $this->assertSame('bunny', $srvRecord->dns_provider);
        $this->assertSame([1, 2], $srvRecord->record_ids);
    }
}
