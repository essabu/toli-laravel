<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Readings;

use DateTimeImmutable;
use Essabu\Toli\Laravel\Models\ToliReading;
use Essabu\Toli\Reading;
use Essabu\Toli\Readings\ReadingStore;
use Essabu\Toli\Readings\StoredReading;
use Essabu\Toli\Readings\Subject;
use Essabu\Toli\Thresholds;

/** The SDK's store contract, on the `toli_readings` table. */
final readonly class EloquentReadingStore implements ReadingStore
{
    public function __construct(private Thresholds $thresholds) {}

    public function find(Subject $subject, string $model): ?StoredReading
    {
        $row = ToliReading::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->where('model', $model)
            ->first();

        return $row instanceof ToliReading ? $this->fromRow($row) : null;
    }

    public function save(Subject $subject, string $model, Reading $reading): StoredReading
    {
        // updateOrCreate on the unique key: a re-read replaces, and a race
        // between two workers reading the same subject ends with one row,
        // which is what the unique index is there to guarantee.
        $row = ToliReading::query()->updateOrCreate(
            ['subject_type' => $subject->type, 'subject_id' => $subject->id, 'model' => $model],
            [
                'served_model' => $reading->model,
                'answers' => $reading->toArray()['answers'],
                'usage' => $reading->usage,
                'read_at' => now(),
            ],
        );

        return $this->fromRow($row->refresh());
    }

    public function forget(Subject $subject, string $model): void
    {
        ToliReading::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->where('model', $model)
            ->delete();
    }

    private function fromRow(ToliReading $row): StoredReading
    {
        $reading = Reading::parse(
            ['model' => $row->served_model, 'answers' => $row->answers, 'usage' => $row->usage],
            $row->model,
            $this->thresholds,
            self::kindsOf($row->answers),
        );

        return new StoredReading(
            Subject::of($row->subject_type, $row->subject_id),
            $row->model,
            $reading,
            DateTimeImmutable::createFromInterface($row->read_at),
        );
    }

    /**
     * The kind of each kept answer, inferred from its wire shape. The three
     * built-ins are told apart by their own field; anything else was kept
     * with its kind alongside, by `Reading::toArray()`.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     */
    private static function kindsOf(array $answers): array
    {
        $kinds = [];
        foreach ($answers as $name => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $kinds[(string) $name] = match (true) {
                array_key_exists('choice', $raw) => 'choice',
                array_key_exists('score', $raw) => 'score',
                array_key_exists('noul', $raw) => 'noul',
                default => (string) ($raw['_kind'] ?? 'generic'),
            };
        }

        return $kinds;
    }
}
