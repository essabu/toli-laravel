<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Events;

use Essabu\Toli\Readings\StoredReading;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Toli has read something. Two channels: one for the subject, so the screen
 * showing it updates, and one for the subject type, so a list does.
 *
 * What travels is the decision — subject, model, answers, routes — and never
 * the state that was sent. That is the caller's data, and a socket is not the
 * place to repeat it.
 */
final class ReadingRecorded implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly StoredReading $stored) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("toli.{$this->stored->subject->type}"),
            new PrivateChannel("toli.{$this->stored->subject->type}.{$this->stored->subject->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'toli.reading.recorded';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $routes = [];
        foreach ($this->stored->reading->answers as $name => $answer) {
            $routes[$name] = $answer->route->value;
        }

        return [
            'subject' => ['type' => $this->stored->subject->type, 'id' => $this->stored->subject->id],
            'model' => $this->stored->model,
            'served_model' => $this->stored->reading->model,
            'answers' => $this->stored->reading->toArray()['answers'],
            'routes' => $routes,
            'read_at' => $this->stored->readAt->format(DATE_ATOM),
        ];
    }
}
