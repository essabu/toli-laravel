<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One reading per subject and model.
 *
 * This is the canonical table from `spec/readings.md`, and the unique key on
 * (subject_type, subject_id, model) is the whole point of it: it is what
 * turns "never read twice" from a habit into something the database refuses
 * to let happen.
 *
 * `answers` is kept in its WIRE shape, not parsed — so an SDK released after
 * this row was written can still read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('toli.table', 'toli_readings'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_type', 64);
            $table->string('subject_id', 191);
            $table->string('model', 64)->comment('the model ASKED for, which is the pinning key');
            $table->string('served_model', 64)->comment('the model the gateway reported serving');
            $table->json('answers');
            $table->json('usage');
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'model'], 'toli_readings_one_per_subject_model');
            $table->index(['subject_type', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('toli.table', 'toli_readings'));
    }
};
