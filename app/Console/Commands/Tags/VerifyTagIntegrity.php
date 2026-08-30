<?php

namespace App\Console\Commands\Tags;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyTagIntegrity extends Command
{
    protected $signature = 'olm:verify-tag-integrity
        {--fix : Rebuild stale CLO pointers and clear invalid type ids}
        {--photo-id= : Check a specific photo only}';

    protected $description = 'Detect taxonomy gaps and stale CLO pointers on photo_tags';

    public function handle(): int
    {
        $this->info('Verifying photo_tags integrity...');

        $unsanctioned = $this->checkUnsanctionedPairings();
        $stalePointers = $this->checkStalePointers();
        $invalidTypes = $this->checkInvalidTypes();

        $total = $unsanctioned + $stalePointers + $invalidTypes;

        if ($total === 0) {
            $this->info('All photo_tags are valid. 0 issues found.');

            return self::SUCCESS;
        }

        $this->warn("{$total} total issue(s) found.");

        if (! $this->option('fix')) {
            $this->line('Run with --fix to auto-repair.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Object tags whose (category_id, litter_object_id) pairing has no row in the pivot.
     *
     * This is the real integrity gap: the observation is valid but the taxonomy never sanctioned
     * the pairing, so nothing can derive a CLO for it. Not auto-repairable — creating the pivot is
     * a taxonomy decision that belongs to an approved migration, never to a repair command.
     */
    private function checkUnsanctionedPairings(): int
    {
        $query = DB::table('photo_tags as pt')
            ->leftJoin('category_litter_object as clo', function ($join) {
                $join->on('clo.category_id', '=', 'pt.category_id')
                    ->on('clo.litter_object_id', '=', 'pt.litter_object_id');
            })
            ->whereNotNull('pt.litter_object_id')
            ->whereNotNull('pt.category_id')
            ->whereNull('clo.id');

        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        $count = $query->count();

        if ($count > 0) {
            $this->error("{$count} photo_tags have a (category, object) pairing with no CLO pivot.");
            $this->line('  Each pairing needs an approved migration — --fix will not invent taxonomy.');
        } else {
            $this->info('Category/object pairings: OK');
        }

        return $count;
    }

    /**
     * Rows whose deprecated `category_litter_object_id` disagrees with the pairing.
     *
     * `category_id` + `litter_object_id` are the source of truth, so the repair rebuilds the
     * pointer from them — never the reverse. A null pointer is not counted: the column is
     * deprecated and unset is a valid state for it.
     */
    private function checkStalePointers(): int
    {
        $query = DB::table('photo_tags as pt')
            ->join('category_litter_object as clo', 'clo.id', '=', 'pt.category_litter_object_id')
            ->where(function ($q) {
                $q->whereColumn('pt.category_id', '!=', 'clo.category_id')
                    ->orWhereColumn('pt.litter_object_id', '!=', 'clo.litter_object_id');
            });

        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        $count = $query->count();

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
     * Type ids not valid for the tag's category/object pairing. The valid-type set hangs off the
     * CLO, so the pairing is resolved to a CLO first rather than trusting the deprecated pointer.
     */
    private function checkInvalidTypes(): int
    {
        $invalid = function ($query) {
            $query->whereNotNull('pt.litter_object_type_id')
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('category_litter_object as clo')
                        ->join('category_object_types as cot', 'cot.category_litter_object_id', '=', 'clo.id')
                        ->whereColumn('clo.category_id', 'pt.category_id')
                        ->whereColumn('clo.litter_object_id', 'pt.litter_object_id')
                        ->whereColumn('cot.litter_object_type_id', 'pt.litter_object_type_id');
                });
        };

        $query = DB::table('photo_tags as pt')->where($invalid);

        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('Type references: OK');

            return 0;
        }

        $this->error("{$count} photo_tags have a type_id not valid for their category/object pairing.");

        if ($this->option('fix')) {
            $this->info('Clearing invalid type ids...');

            $fixed = DB::table('photo_tags as pt')
                ->where($invalid)
                ->update(['litter_object_type_id' => null]);

            $this->info("Cleared type_id on {$fixed} rows.");
        }

        return $count;
    }
}
