<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Readings;

use Essabu\Toli\Laravel\Events\ReadingRecorded;
use Essabu\Toli\Readings\ReadingBroadcast;
use Essabu\Toli\Readings\StoredReading;

/** The SDK's broadcast contract, as a Laravel event. */
final class EventReadingBroadcast implements ReadingBroadcast
{
    public function recorded(StoredReading $stored): void
    {
        ReadingRecorded::dispatch($stored);
    }
}
