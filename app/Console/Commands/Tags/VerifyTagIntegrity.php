<?php

namespace App\Console\Commands\Tags;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class VerifyTagIntegrity extends Command
{
    protected $signature = 'olm:verify-tag-integrity
        {--fix : Rebuild stale CLO pointers and clear invalid type ids}
        {--photo-id= : Check a specific photo only}';

    protected $description = 'Detect taxonomy gaps, stale CLO pointers, rows and quick tags left on tombstoned pairings, and broken retirement chains';

    public function handle(): int
    {
        $this->info('Verifying photo_tags integrity...');

        $unsanctioned = $this->checkUnsanctionedPairings();
        $stalePointers = $this->checkStalePointers();
        $invalidTypes = $this->checkInvalidTypes();
        $onTombstones = $this->checkRowsOnTombstones();

        // - --photo-id checks that photo only; skip account-wide quick tags and CLO redirect loops.
        $quickTagsOnTombstones = $this->option('photo-id') ? 0 : $this->checkQuickTagsOnTombstones();
        $cycles = $this->option('photo-id') ? 0 : $this->checkRetirementCycles();

        $total = $unsanctioned + $stalePointers + $invalidTypes + $onTombstones + $quickTagsOnTombstones + $cycles;

        if ($total === 0) {
            $this->info('All photo_tags are valid. 0 issues found.');

            return self::SUCCESS;
        }

        $this->warn("{$total} total issue(s) found.");

        if (! $this->option('fix')) {
            $this->line('Run with --fix to auto-repair.');

            return self::FAILURE;
        }

        // - Recount after --fix and return failure if any issues remain.
        // - Example: --fix cannot resolve a missing CLO or finish an object retirement.
        $remaining = $this->unsanctionedPairings()->count()
            + $this->stalePointers()->count()
            + $this->invalidTypes()->count()
            + $this->rowsOnTombstones()->count()
            + ($this->option('photo-id') ? 0 : $this->quickTagsOnTombstones()->count())
            + ($this->option('photo-id') ? 0 : $this->retirementCycles());

        if ($remaining > 0) {
            $this->error("{$remaining} issue(s) remain after repair.");

            return self::FAILURE;
        }

        $this->info('All repairable issues fixed.');

        return self::SUCCESS;
    }

    /**
     * - Find photo tags whose category_id and litter_object_id have no matching CLO.
     * - Example: a tag records an object in a category where no CLO was declared.
     * - Report these for an approved cleanup decision; --fix does not create CLOs.
     */
    private function unsanctionedPairings(): Builder
    {
        return $this->scopeToPhoto(
            DB::table('photo_tags as pt')
                ->leftJoin('category_litter_object as clo', function ($join) {
                    $join->on('clo.category_id', '=', 'pt.category_id')
                        ->on('clo.litter_object_id', '=', 'pt.litter_object_id');
                })
                ->whereNotNull('pt.litter_object_id')
                ->whereNotNull('pt.category_id')
                ->whereNull('clo.id')
        );
    }

    /**
     * - Find stored CLO IDs that disagree with the photo tag's category or object.
     * - Example: category_litter_object_id points to alcohol/bottle but the tag records softdrinks/bottle.
     */
    private function stalePointers(): Builder
    {
        return $this->scopeToPhoto(
            DB::table('photo_tags as pt')
                ->join('category_litter_object as clo', 'clo.id', '=', 'pt.category_litter_object_id')
                ->where(function ($q) {
                    $q->whereColumn('pt.category_id', '!=', 'clo.category_id')
                        ->orWhereColumn('pt.litter_object_id', '!=', 'clo.litter_object_id');
                })
        );
    }

    /**
     * - Find photo tags whose type is not allowed on their category/object's CLO.
     * - Example: a tag has type beer but its CLO has no beer entry in category_object_types.
     * - Skip missing CLOs; they are reported separately and need an approved cleanup decision.
     */
    private function invalidTypes(): Builder
    {
        return $this->scopeToPhoto(
            DB::table('photo_tags as pt')
                ->whereNotNull('pt.litter_object_type_id')
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('category_litter_object as clo')
                        ->whereColumn('clo.category_id', 'pt.category_id')
                        ->whereColumn('clo.litter_object_id', 'pt.litter_object_id');
                })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('category_litter_object as clo')
                        ->join('category_object_types as cot', 'cot.category_litter_object_id', '=', 'clo.id')
                        ->whereColumn('clo.category_id', 'pt.category_id')
                        ->whereColumn('clo.litter_object_id', 'pt.litter_object_id')
                        ->whereColumn('cot.litter_object_type_id', 'pt.litter_object_type_id');
                })
        );
    }

    /**
     * - Find photo tags still using the category/object of a retired CLO.
     * - Example: CLO 10 redirects to 20, but a photo tag still uses CLO 10's category/object.
     * - Report these so the mapping can be rerun; --fix does not move them.
     */
    private function rowsOnTombstones(): Builder
    {
        return $this->scopeToPhoto(
            DB::table('photo_tags as pt')
                ->join('category_litter_object as clo', function ($join) {
                    $join->on('clo.category_id', '=', 'pt.category_id')
                        ->on('clo.litter_object_id', '=', 'pt.litter_object_id');
                })
                ->whereNotNull('clo.merged_into_clo_id')
        );
    }

    /**
     * - Find saved quick tags whose clo_id still references a retired CLO.
     * - Example: CLO 10 redirects to 20, but the quick tag still stores clo_id = 10.
     */
    private function quickTagsOnTombstones(): Builder
    {
        return DB::table('user_quick_tags as uqt')
            ->join('category_litter_object as clo', 'clo.id', '=', 'uqt.clo_id')
            ->whereNotNull('clo.merged_into_clo_id');
    }

    /**
     * - Count CLO redirects that lead into a loop, e.g. CLO 10 → 20 → 10.
     * - Save requests return 422 for these loops because no active CLO can be reached.
     */
    private function retirementCycles(): int
    {
        $next = DB::table('category_litter_object')
            ->whereNotNull('merged_into_clo_id')
            ->pluck('merged_into_clo_id', 'id')
            ->map('intval')
            ->all();
        $broken = 0;

        foreach (array_keys($next) as $start) {
            $seen = [];
            $clo = $start;

            while (isset($next[$clo])) {
                if (isset($seen[$clo])) {
                    $broken++;
                    break;
                }

                $seen[$clo] = true;
                $clo = $next[$clo];
            }
        }

        return $broken;
    }

    private function scopeToPhoto(Builder $query): Builder
    {
        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        return $query;
    }

    private function checkUnsanctionedPairings(): int
    {
        $count = $this->unsanctionedPairings()->count();

        if ($count > 0) {
            $this->error("{$count} photo_tags have a (category, object) pairing with no CLO pivot.");
            $this->line('  Each pairing needs an approved migration — --fix will not invent taxonomy.');
        } else {
            $this->info('Category/object pairings: OK');
        }

        return $count;
    }

    /**
     * - Report stored category_litter_object_id values that disagree with the tag's category/object.
     * - With --fix, rebuild that deprecated ID from category_id and litter_object_id.
     * - Never change the category or object to match the stored CLO ID.
     * - Ignore null stored CLO IDs; they are valid for this deprecated column.
     */
    private function checkStalePointers(): int
    {
        $count = $this->stalePointers()->count();

        if ($count === 0) {
            $this->info('CLO pointers: OK');

            return 0;
        }

        $this->error("{$count} photo_tags have a CLO pointer that disagrees with their pairing.");

        if ($this->option('fix')) {
            $this->info('Rebuilding stale pointers from (category_id, litter_object_id)...');

            $fixed = DB::update('
                UPDATE photo_tags pt
                JOIN category_litter_object stale ON stale.id = pt.category_litter_object_id
                JOIN category_litter_object correct
                    ON correct.category_id = pt.category_id
                   AND correct.litter_object_id = pt.litter_object_id
                SET pt.category_litter_object_id = correct.id
                WHERE pt.category_id != stale.category_id
                   OR pt.litter_object_id != stale.litter_object_id
            ');

            $this->info("Rebuilt {$fixed} pointer(s).");
        }

        return $count;
    }

    /**
     * - Report photo tags that remain on retired CLOs.
     * - Ask for the mapping to be rerun; this check does not move tags or change types.
     */
    private function checkRowsOnTombstones(): int
    {
        $count = $this->rowsOnTombstones()->count();

        if ($count > 0) {
            $this->error("{$count} photo_tags remain on a tombstoned pairing.");
            $this->line('  Re-run the mapping that retired it — the migration did not finish.');
        } else {
            $this->info('Rows on tombstoned pairings: OK');
        }

        return $count;
    }

    private function checkQuickTagsOnTombstones(): int
    {
        $count = $this->quickTagsOnTombstones()->count();

        if ($count > 0) {
            $this->error("{$count} quick tag(s) still point at a tombstoned pairing.");
        } else {
            $this->info('Quick tags on tombstoned pairings: OK');
        }

        return $count;
    }

    private function checkRetirementCycles(): int
    {
        $count = $this->retirementCycles();

        if ($count > 0) {
            $this->error("{$count} retirement chain(s) form a cycle and never reach an active pairing.");
        } else {
            $this->info('Retirement chains: OK');
        }

        return $count;
    }

    private function checkInvalidTypes(): int
    {
        $count = $this->invalidTypes()->count();

        if ($count === 0) {
            $this->info('Type references: OK');

            return 0;
        }

        $this->error("{$count} photo_tags have a type_id not valid for their category/object pairing.");

        if ($this->option('fix')) {
            $this->info('Clearing invalid type ids...');

            $fixed = $this->invalidTypes()->update(['litter_object_type_id' => null]);

            $this->info("Cleared type_id on {$fixed} rows.");
        }

        return $count;
    }
}
