<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Tests;

use Essabu\Toli\Laravel\Events\ReadingRecorded;
use Essabu\Toli\Laravel\Models\ToliReading;
use Essabu\Toli\Laravel\ToliServiceProvider;
use Essabu\Toli\Question\Choice;
use Essabu\Toli\Question\Noul;
use Essabu\Toli\Question\Question;
use Essabu\Toli\Readings\Reader;
use Essabu\Toli\Readings\ReadingStore;
use Essabu\Toli\Readings\Subject;
use Essabu\Toli\Route;
use Essabu\Toli\Toli;
use Essabu\Toli\Transport\Gateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;

/**
 * What installing the package gives an application, held end to end: the
 * table appears with `migrate`, one reading per subject and model is enforced
 * by the row, and a recorded reading is an event.
 */
final class PackageTest extends TestCase
{
    private MockHandler $http;

    protected function getPackageProviders($app): array
    {
        return [ToliServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:']);
        $app['config']->set('toli.key', 'toli_test');
        $app['config']->set('broadcasting.default', 'null');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate')->run();

        // The container's Toli, but on a scripted HTTP client.
        $this->http = new MockHandler;
        $factory = new HttpFactory;
        $this->app->instance(Toli::class, new Toli(
            apiKey: 'toli_test',
            http: new Client(['handler' => HandlerStack::create($this->http)]),
            requests: $factory,
            streams: $factory,
            transport: new Gateway,
            sleeper: static fn (): null => null,
        ));
    }

    public function test_migrate_creates_the_table_with_its_unique_key(): void
    {
        $this->assertTrue(\Schema::hasTable('toli_readings'));
        $this->assertTrue(\Schema::hasColumns('toli_readings', ['subject_type', 'subject_id', 'model', 'served_model', 'answers', 'usage', 'read_at']));
    }

    public function test_a_reading_is_kept_once_and_broadcast_once(): void
    {
        Event::fake([ReadingRecorded::class]);
        $this->http->append($this->served(), $this->served());

        $reader = $this->app->make(Reader::class);
        $first = $reader->read(Subject::of('ticket', 'TK-1'), 'A state.', $this->questions());
        $second = $reader->read(Subject::of('ticket', 'TK-1'), 'A state, edited.', $this->questions());

        $this->assertFalse($first->fromStore);
        $this->assertTrue($second->fromStore, 'The second read is served from the table.');
        $this->assertSame(1, $this->http->count(), 'One scripted response left: only one call went out.');
        $this->assertSame(1, ToliReading::query()->count());
        Event::assertDispatchedTimes(ReadingRecorded::class, 1);
    }

    public function test_a_kept_reading_comes_back_with_its_routes(): void
    {
        $this->http->append($this->served());
        $reader = $this->app->make(Reader::class);
        $reader->read(Subject::of('ticket', 'TK-1'), 'A state.', $this->questions());

        $kept = $this->app->make(ReadingStore::class)->find(Subject::of('ticket', 'TK-1'), 'toli-1');

        $this->assertNotNull($kept);
        $this->assertSame('app', $kept->reading->value('team'));
        $this->assertSame(Route::Act, $kept->reading->route('team'));
        $this->assertSame('toli-1.14', $kept->reading->model, 'The served model survives the round trip.');
        $this->assertSame(['input_tokens' => 120, 'output_tokens' => 0], $kept->reading->usage);
    }

    public function test_the_event_carries_the_decision_and_not_the_state(): void
    {
        Event::fake([ReadingRecorded::class]);
        $this->http->append($this->served());

        $this->app->make(Reader::class)->read(Subject::of('ticket', 'TK-1'), 'A sentence that must not travel.', $this->questions());

        Event::assertDispatched(ReadingRecorded::class, function (ReadingRecorded $event): bool {
            $payload = $event->broadcastWith();
            $this->assertSame(['type' => 'ticket', 'id' => 'TK-1'], $payload['subject']);
            $this->assertSame('act', $payload['routes']['team']);
            $this->assertStringNotContainsString('must not travel', json_encode($payload, JSON_THROW_ON_ERROR));
            $this->assertSame('private-toli.ticket.TK-1', $event->broadcastOn()[1]->name);

            return true;
        });
    }

    public function test_reading_again_replaces_the_row_rather_than_adding_one(): void
    {
        $this->http->append($this->served(), $this->served('other'));
        $reader = $this->app->make(Reader::class);

        $reader->read(Subject::of('ticket', 'TK-1'), 'A state.', $this->questions());
        $reader->read(Subject::of('ticket', 'TK-1'), 'A state, with a comment.', $this->questions(), again: true);

        $this->assertSame(1, ToliReading::query()->count());
        $this->assertSame('other', $this->app->make(ReadingStore::class)->find(Subject::of('ticket', 'TK-1'), 'toli-1')?->reading->value('team'));
    }

    private function served(string $team = 'app'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'model' => 'toli-1.14',
            'answers' => [
                'team' => ['choice' => $team, 'confidence' => 0.9, 'probabilities' => ['app' => 0.9, 'other' => 0.1]],
                'urgent' => ['noul' => 0.8],
            ],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 0],
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, Question> */
    private function questions(): array
    {
        return [
            'team' => new Choice('Which team', ['app' => 'The app', 'other' => 'None']),
            'urgent' => new Noul('Still blocked'),
        ];
    }
}
