<?php

namespace App\Console\Commands\Tags;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RebuildTagCounts extends Command
{
    protected $signature = 'olm:rebuild-tag-counts {--scope=total : "total" (internal, all recorded tags) or "public" (verified >= 2 AND is_public — what is visible on the map)}';

    protected $description = 'Rebuild a committed tag counts JSON (recorded tags per object/category/type). --scope=total writes the internal file, --scope=public writes the public on-map file.';

    public function handle(): int
    {
        $scope = $this->option('scope');

        if (! in_array($scope, ['total', 'public'], true)) {
            $this->error("Invalid --scope '{$scope}'. Use 'total' or 'public'.");

            return self::FAILURE;
        }

        [$path, $scopeLabel] = $scope === 'public'
            ? [config('tags.public_counts_path'), 'verified_public_on_map']
            : [config('tags.usage_counts_path'), 'total_recorded_tags'];

        $this->info('Aggregating tag usage counts...');

        $query = DB::table('photo_tags as pt')
            ->join('photos as p', 'p.id', '=', 'pt.photo_id')
            ->whereNull('p.deleted_at')
            ->whereNotNull('pt.litter_object_id');

        if ($scope === 'public') {
            $query->where('p.is_public', 1)
                ->where('p.verified', '>=', 2);
        }

        $rows = $query
            ->groupBy('pt.litter_object_id', 'pt.category_id', 'pt.litter_object_type_id')
            ->select(
                'pt.litter_object_id',
                'pt.category_id',
                'pt.litter_object_type_id',
                DB::raw('COUNT(*) as cnt')
            )
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = $row->litter_object_id.':'.$row->category_id.':'.($row->litter_object_type_id ?? 0);
            $counts[$key] = (int) $row->cnt;
        }

        $payload = [
            'generated_at' => now()->toDateString(),
            'scope' => $scopeLabel,
            'counts' => $counts,
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info(count($counts).' distinct (object, category, type) keys written to '.$path);

        return self::SUCCESS;
    }
}
