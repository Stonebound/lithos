<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ManageSrvRecords;
use App\Models\SrvRecord;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageSrvRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.bunnynet.api_key', 'fake_api_key');
        Config::set('services.bunnynet.base_domain', 'example.com');
        Queue::fake();

        // Bind a mocked Guzzle client
        $responses = [];
        for ($i = 0; $i < 20; $i++) {
            $responses[] = new Response(200, [], json_encode(['Id' => 456, 'success' => true]));
        }
        $responses[0] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'example.com']]]));
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->app->bind(Client::class, fn () => $client);
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

        $job = new ManageSrvRecords($srvRecord, 'create');

        $job->handle();

        // Assert that the record_ids were updated
        $srvRecord->refresh();
        $this->assertContains(456, $srvRecord->record_ids);
    }

    #[Test]
    public function test_job_handles_update_with_port_change(): void
    {
        $srvRecord = SrvRecord::factory()->create([
            'subdomain' => 'test',
            'port' => 25565,
            'record_ids' => [1, 2],
        ]);

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
            'record_ids' => [1, 2],
        ]);

        $job = new ManageSrvRecords($srvRecord, 'update', ['subdomain' => 'new']);

        $job->handle();

        $this->assertEquals('update', $job->action);
        $this->assertArrayHasKey('subdomain', $job->changes);
    }

    #[Test]
    public function test_job_handles_delete_action(): void
    {
        $srvRecord = SrvRecord::factory()->create(['record_ids' => [1, 2]]);

        $job = new ManageSrvRecords($srvRecord, 'delete');

        $job->handle();

        $this->assertEquals('delete', $job->action);
    }
}
