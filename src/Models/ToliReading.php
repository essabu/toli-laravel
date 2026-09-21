<?php

declare(strict_types=1);

namespace Essabu\Toli\Laravel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A kept reading, as a row.
 *
 * Thin on purpose: the behaviour lives in the SDK's `Reader`, and this model
 * is how the Eloquent store gets a row in and out. An application that wants
 * to query readings — how many, how recent, which answered what — queries
 * this, and `answers` is the wire shape the SDK's `Reading::parse` reads.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $model
 * @property string $served_model
 * @property array<string, mixed> $answers
 * @property array{input_tokens: int, output_tokens: int} $usage
 * @property Carbon $read_at
 */
final class ToliReading extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'answers' => 'array',
        'usage' => 'array',
        'read_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('toli.table', 'toli_readings');
    }
}
