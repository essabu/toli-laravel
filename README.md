# Toli for Laravel

> Read-only mirror of [essabu/toli-sdk](https://github.com/essabu/toli-sdk) — every fix happens there.

The [Toli](https://toli.essabu.com) SDK wired into a Laravel application, with
the one table it needs and the event it fires.

```sh
composer require essabu/toli-laravel
php artisan migrate          # creates toli_readings
```

Set `TOLI_API_KEY`, and optionally `TOLI_MODEL` (default `toli-1`).

## What you get

| | |
|---|---|
| `Essabu\Toli\Toli` | the client, built from `config/toli.php` |
| `Essabu\Toli\Readings\Reader` | **read once, keep it, tell the room** |
| `toli_readings` | one row per subject and model — the unique key is what makes "never read twice" a rule the database enforces |
| `ReadingRecorded` | a broadcast event on `private-toli.{type}` and `private-toli.{type}.{id}`, carrying the decision and never the state |
| `php artisan toli:kinds` | the catalogue of question kinds the gateway answers |

## Reading something

```php
use Essabu\Toli\Question\Choice;
use Essabu\Toli\Question\Noul;
use Essabu\Toli\Readings\Reader;
use Essabu\Toli\Readings\Subject;

$stored = app(Reader::class)->read(
    Subject::of('ticket', $ticket->code),
    $ticket->stateForToli(),
    [
        'team'   => new Choice('Which team handles this', [...]),
        'urgent' => new Noul('The situation is still blocking today'),
    ],
);

$stored->reading->route('team');   // Route::Act | Confirm | Escalate
$stored->fromStore;                // true when nothing went to the gateway
```

A second call for the same subject and model returns the kept reading and
never reaches the gateway. Pass `again: true` to re-read — after the questions
changed, never as the default.

## The table

`subject_type`, `subject_id`, `model` (the one you asked for — the pinning
key), `served_model` (what the gateway reported), `answers` in wire shape,
`usage`, `read_at`. Unique on the first three.

`answers` is kept as served, not parsed, so an SDK released after a row was
written can still read it.

## What this package does not do

It does not write your questions and it does not do your analysis. Which
questions to ask a ticket, and what to conclude from a thousand answers, is
your business; this is the part that is the same for everyone.
