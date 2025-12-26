<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SrvRecords\Pages\CreateSrvRecord;
use App\Filament\Resources\SrvRecords\Pages\EditSrvRecord;
use App\Filament\Resources\SrvRecords\Pages\ListSrvRecords;
use App\Jobs\ManageSrvRecords;
use App\Models\SrvRecord;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SrvRecordResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.bunnynet.api_key', 'fake_api_key');

        // Bind a mocked Guzzle client for all tests
        $mockResponses = [];
        // Helper: for each SRV record operation, we need a zone lookup, then an add for base, then an add for each additional_subdomain (here: 'la')
        $additionalSubdomains = ['la'];
        $id = 456;

        // list_srv_records: 2 records, each triggers base + 1 additional
        for ($i = 0; $i < 2; $i++) {
            $mockResponses[] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'stonebound.net']]])); // zone lookup
            $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add base
            foreach ($additionalSubdomains as $sub) {
                $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add additional
            }
        }

        // create_srv_record: base + 1 additional
        $mockResponses[] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'stonebound.net']]]));
        $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add base
        foreach ($additionalSubdomains as $sub) {
            $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add additional
        }

        // edit_srv_record: delete (base + additional), then add (base + additional)
        $mockResponses[] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'stonebound.net']]])); // zone lookup for delete
        $mockResponses[] = new Response(200, [], json_encode(['success' => true])); // delete base
        foreach ($additionalSubdomains as $sub) {
            $mockResponses[] = new Response(200, [], json_encode(['success' => true])); // delete additional
        }
        $mockResponses[] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'stonebound.net']]])); // zone lookup for add
        $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add base
        foreach ($additionalSubdomains as $sub) {
            $mockResponses[] = new Response(200, [], json_encode(['Id' => $id++])); // add additional
        }

        // delete_srv_record: delete (base + additional)
        $mockResponses[] = new Response(200, [], json_encode(['Items' => [['Id' => 123, 'Domain' => 'stonebound.net']]]));
        $mockResponses[] = new Response(200, [], json_encode(['success' => true])); // delete base
        foreach ($additionalSubdomains as $sub) {
            $mockResponses[] = new Response(200, [], json_encode(['success' => true])); // delete additional
        }

        $mock = new MockHandler($mockResponses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->app->bind(Client::class, fn () => $client);
    }

    #[Test]
    public function test_list_srv_records(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        SrvRecord::factory()->create(['subdomain' => 'test1', 'port' => 25565]);
        SrvRecord::factory()->create(['subdomain' => 'test2', 'port' => 25566]);

        Livewire::test(ListSrvRecords::class)
            ->assertCanSeeTableRecords(SrvRecord::all())
            ->assertCountTableRecords(2);
    }

    #[Test]
    public function test_create_srv_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        Queue::fake();

        Livewire::test(CreateSrvRecord::class)
            ->fillForm([
                'subdomain' => 'newserver',
                'port' => 25565,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('srv_records', [
            'subdomain' => 'newserver',
            'port' => 25565,
        ]);

        Queue::assertPushed(ManageSrvRecords::class, function ($job) {
            return $job->action === 'create';
        });
    }

    #[Test]
    public function test_edit_srv_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        /** @var SrvRecord $record */
        $record = SrvRecord::factory()->create(['subdomain' => 'oldserver', 'port' => 25565]);

        Queue::fake();

        Livewire::test(EditSrvRecord::class, ['record' => $record->id])
            ->fillForm([
                'subdomain' => 'newserver',
                'port' => 25566,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertEquals('newserver', $record->subdomain);
        $this->assertEquals(25566, $record->port);

        Queue::assertPushed(ManageSrvRecords::class, function ($job) {
            return $job->action === 'update' && isset($job->changes['subdomain']) && isset($job->changes['port']);
        });
    }

    #[Test]
    public function test_delete_srv_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'maintainer']);
        $this->actingAs($user);

        /** @var SrvRecord $record */
        $record = SrvRecord::factory()->create();

        Queue::fake();

        Livewire::test(ListSrvRecords::class)
            ->callTableAction('delete', $record);

        $this->assertModelMissing($record);

        Queue::assertPushed(ManageSrvRecords::class, function ($job) {
            return $job->action === 'delete';
        });
    }
}
