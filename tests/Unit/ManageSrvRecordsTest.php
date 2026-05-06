<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ManageSrvRecords;
use App\Models\SrvRecord;
use App\Services\Dns\SrvDnsProvider;
use App\Services\Dns\SrvDnsProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ManageSrvRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    #[Test]
    public function test_job_constructs_correctly(): void
    {
        $srvRecord = SrvRecord::factory()->create();
        $changes = ['port' => 25566];

        $job = new ManageSrvRecords($srvRecord, 'update', $changes);

        $this->assertEquals($srvRecord->id, $job->srvRecord->id);
        $this->assertEquals('update', $job->action);
        $this->assertEquals($changes, $job->changes);
    }

    #[Test]
    public function test_job_handles_create_action(): void
    {
        $srvRecord = SrvRecord::factory()->create(['subdomain' => 'test', 'port' => 25565]);

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('createRecords')->once()->with(Mockery::type(SrvRecord::class))->andReturn([456, 457]);

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $job = new ManageSrvRecords($srvRecord, 'create');

        $job->handle();

        $srvRecord->refresh();
        $this->assertSame([456, 457], $srvRecord->record_ids);
        $this->assertSame('hetzner', $srvRecord->dns_provider);
    }

    #[Test]
    public function test_job_handles_update_with_port_change(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'test',
            'port' => 25565,
            'dns_provider' => 'bunny',
            'record_ids' => [1, 2],
        ]);

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('updateRecords')->once()->with(Mockery::type(SrvRecord::class));

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('bunny');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $job = new ManageSrvRecords($srvRecord, 'update', ['port' => 25566]);

        $job->handle();

        $this->assertEquals('update', $job->action);
        $this->assertArrayHasKey('port', $job->changes);
    }

    #[Test]
    public function test_job_handles_update_with_subdomain_change(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'old',
            'port' => 25565,
            'dns_provider' => 'hetzner',
            'record_ids' => [1, 2],
        ]);

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('deleteRecords')->once()->with(Mockery::type(SrvRecord::class));
        $provider->shouldReceive('createRecords')->once()->with(Mockery::type(SrvRecord::class))->andReturn(['new/SRV']);

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $job = new ManageSrvRecords($srvRecord, 'update', ['subdomain' => 'new']);

        $job->handle();

        $srvRecord->refresh();
        $this->assertSame(['new/SRV'], $srvRecord->record_ids);
        $this->assertSame('hetzner', $srvRecord->dns_provider);
        $this->assertEquals('update', $job->action);
        $this->assertArrayHasKey('subdomain', $job->changes);
    }

    #[Test]
    public function test_job_handles_delete_action(): void
    {
        $srvRecord = SrvRecord::factory()->create(['dns_provider' => 'bunny', 'record_ids' => [1, 2]]);

        $provider = Mockery::mock(SrvDnsProvider::class);
        $provider->shouldReceive('deleteRecords')->once()->with(Mockery::type(SrvRecord::class));

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('bunny');
        $resolver->shouldReceive('resolve')->once()->andReturn($provider);
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $job = new ManageSrvRecords($srvRecord, 'delete');

        $job->handle();

        $this->assertEquals('delete', $job->action);
    }

    #[Test]
    public function test_job_fails_when_record_provider_is_missing(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'record_ids' => [1, 2],
            'dns_provider' => null,
        ]);

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('bunny');
        $resolver->shouldNotReceive('resolve');
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no provider');

        (new ManageSrvRecords($srvRecord, 'delete'))->handle();
    }

    #[Test]
    public function test_job_fails_when_record_provider_does_not_match_active_provider(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'record_ids' => [1, 2],
            'dns_provider' => 'bunny',
        ]);

        $resolver = Mockery::mock(SrvDnsProviderResolver::class);
        $resolver->shouldReceive('providerName')->once()->andReturn('hetzner');
        $resolver->shouldNotReceive('resolve');
        $this->app->instance(SrvDnsProviderResolver::class, $resolver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('managed by [bunny]');

        (new ManageSrvRecords($srvRecord, 'update', ['port' => 25566]))->handle();
    }
}
