<?php

namespace App\Console\Commands\Tags;

use App\Models\Litter\Tags\CategoryObject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyTagIntegrity extends Command
{
    protected $signature = 'olm:verify-tag-integrity
        {--fix : Auto-repair mismatched denorm fields}
        {--photo-id= : Check a specific photo only}';

    protected $description = 'Detect and optionally repair CLO ↔ denorm field drift on photo_tags';

    public function handle(): int
    {
        $this->info('Verifying photo_tags integrity...');

        $orphanedClo = $this->checkOrphanedClo();
        $denormMismatches = $this->checkDenormMismatches();
        $invalidTypes = $this->checkInvalidTypes();

        $total = $orphanedClo + $denormMismatches + $invalidTypes;

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
     * Check for photo_tags referencing non-existent CLO ids.
     */
    private function checkOrphanedClo(): int
    {
        $query = DB::table('photo_tags as pt')
            ->leftJoin('category_litter_object as clo', 'clo.id', '=', 'pt.category_litter_object_id')
            ->whereNull('clo.id');

        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        $count = $query->count();

        if ($count > 0) {
            $this->error("{$count} photo_tags reference non-existent CLO ids.");
        } else {
            $this->info('CLO references: OK');
        }

        return $count;
    }

    /**
     * Check that category_id and litter_object_id match the referenced CLO.
     */
    private function checkDenormMismatches(): int
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

        if ($count > 0) {
            $this->error("{$count} photo_tags have denorm fields that don't match their CLO.");

            if ($this->option('fix')) {
                $this->info('Repairing denorm mismatches...');

                $fixed = DB::update("
                    UPDATE photo_tags pt
                    JOIN category_litter_object clo ON clo.id = pt.category_litter_object_id
                    SET pt.category_id = clo.category_id,
                        pt.litter_object_id = clo.litter_object_id
                    WHERE pt.category_id != clo.category_id
                       OR pt.litter_object_id != clo.litter_object_id
                ");

                $this->info("Fixed {$fixed} rows.");
            }
        } else {
            $this->info('Denorm fields: OK');
        }

        return $count;
    }

    /**
     * Check that litter_object_type_id is valid for the referenced CLO.
     */
    private function checkInvalidTypes(): int
    {
        $query = DB::table('photo_tags as pt')
            ->whereNotNull('pt.litter_object_type_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('category_object_types as cot')
                    ->whereColumn('cot.category_litter_object_id', 'pt.category_litter_object_id')
                    ->whereColumn('cot.litter_object_type_id', 'pt.litter_object_type_id');
            });

        if ($this->option('photo-id')) {
            $query->where('pt.photo_id', $this->option('photo-id'));
        }

        $count = $query->count();

        if ($count > 0) {
            $this->error("{$count} photo_tags have type_id not valid for their CLO.");

            if ($this->option('fix')) {
                $this->info('Clearing invalid type_ids...');

                $fixed = DB::table('photo_tags as pt')
                    ->whereNotNull('pt.litter_object_type_id')
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('category_object_types as cot')
                            ->whereColumn('cot.category_litter_object_id', 'pt.category_litter_object_id')
                            ->whereColumn('cot.litter_object_type_id', 'pt.litter_object_type_id');
                    })
                    ->update(['litter_object_type_id' => null]);

                $this->info("Cleared type_id on {$fixed} rows.");
            }
        } else {
            $this->info('Type references: OK');
        }

        return $count;
    }
}
