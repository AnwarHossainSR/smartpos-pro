<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A seeder whose whole run is one transaction.
 *
 * Without this every `Model::create()` in a seeder is its own transaction, and every
 * transaction costs a commit. On Postgres that is one fsync; on MySQL with the binary
 * log enabled it is two, plus a doublewrite — which is why seeding was roughly 20x
 * slower there. Batching the whole seeder into one transaction removes the per-row
 * commit entirely.
 *
 * It is also the correct shape regardless of speed: a seeder that fails halfway
 * currently leaves a half-built dataset behind, which is worse than leaving none.
 *
 * Subclasses implement `seed()` instead of `run()`. `run()` stays public so a seeder
 * can still be invoked directly — `(new TaxSeeder($tenantId))->run()` — and nested
 * calls become savepoints rather than a second commit.
 */
abstract class TransactionalSeeder extends Seeder
{
    abstract protected function seed(): void;

    public function run(): void
    {
        DB::transaction($this->seed(...));
    }
}
