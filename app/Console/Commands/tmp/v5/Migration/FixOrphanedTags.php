<?php

namespace App\Console\Commands\tmp\v5\Migration;

use Illuminate\Console\Command;

class FixOrphanedTags extends Command
{
    protected $signature = 'olm:fix-orphaned-tags
        {--apply : Actually execute the updates (dry-run by default)}
        {--verify-only : Run post-apply verification queries only}
        {--batch=5000 : Batch size for chunked updates}
        {--log= : Write output to log file (e.g. storage/logs/orphan-fix.log)}';

    protected $description = 'RETIRED — superseded by olm:migrate-tag. Cannot be executed.';

    /**
     * Refuses unconditionally. Its 75 mappings were never approved, and its `plasticBags` row
     * (149 → 92, CLO 111) encodes the direction D-4 reversed — running it would move data back
     * onto a retired object. Approved retirements go through `olm:migrate-tag`, one entry at a
     * time, against the list in readme/audit/TagRetirements-2026-08.csv.
     *
     * The signature is kept so the refusal answers the invocations an operator would actually
     * type. The body it used to run is unchanged on `master` and in git history — it is not
     * carried here dead.
     */
    public function handle(): int
    {
        $this->error('olm:fix-orphaned-tags is retired and cannot be run.');
        $this->line('Its mappings are unapproved, and the plasticBags row encodes the pre-D-4 direction.');
        $this->line('Use olm:migrate-tag --entry=... instead.');

        return self::FAILURE;
    }
}
