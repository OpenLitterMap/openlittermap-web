<?php

namespace App\Console\Commands\Clusters;

use App\Models\Photo;
use App\Models\TeamCluster;
use App\Models\Teams\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateTeamClusters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clusters:generate-team-clusters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all clusters for teams photos';

    private $clustersDir = 'team-clusters.json';
    private $featuresDir = 'team-features.json';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start = microtime(true);

        $teamsWithImages = Team::where('total_images', '>', 0)->get();

        foreach ($teamsWithImages as $team) {
            $this->line("\n\n<info>Team:</info> $team->name");

            $this->generateFeatures($team);
            $this->deleteClusters($team);
            $this->generateClusters($team);
        }

        $finish = microtime(true);
        $this->newLine();
        $this->info("Total Time: " . ($finish - $start) . "\n");

        return 0;
    }

    /**
     * Generates features.json
     */
    protected function generateFeatures(Team $team): void
    {
        $this->info("Generating features...");

        $bar = $this->output->createProgressBar(
            Photo::whereTeamId($team->id)->count()
        );
        $bar->setFormat('debug');
        $bar->start();

        $photos = Photo::query()
            ->select('lat', 'lon')
            ->whereTeamId($team->id)
            ->cursor();

        $features = [];

        foreach ($photos as $photo) {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ]
            ];

            $features[] = $feature;

            $bar->advance();
        }

        $bar->finish();

        $features = json_encode($features, JSON_NUMERIC_CHECK);
        Storage::put("/data/$this->featuresDir", $features);

        $this->info("\nFeatures finished...");
    }

    /**
     * Generates clusters.json
     */
    protected function generateClusters(Team $team): void
    {
        $this->info("Generating clusters for each zoom level...");

        $rootDir = base_path();
        $zoomLevels = range(2, 16);
        $time = now();

        $bar = $this->output->createProgressBar(16);
        $bar->setFormat('debug');
        $bar->advance();
        $bar->start();

        foreach ($zoomLevels as $zoomLevel) {
            exec("node app/Node/supercluster-php $rootDir $zoomLevel $this->featuresDir $this->clustersDir");

            collect(json_decode(Storage::get("/data/$this->clustersDir")))
                ->filter(function ($cluster) {
                    return isset($cluster->properties);
                })
                ->map(function ($cluster) use ($team, $zoomLevel, $time) {
                    return [
                        'team_id' => $team->id,
                        'zoom' => $zoomLevel,
                        'lat' => $cluster->geometry->coordinates[1],
                        'lon' => $cluster->geometry->coordinates[0],
                        'point_count' => $cluster->properties->point_count,
                        'point_count_abbreviated' => $cluster->properties->point_count_abbreviated,
                        'geohash' => \GeoHash::encode($cluster->geometry->coordinates[1], $cluster->geometry->coordinates[0]),
                        'created_at' => $time,
                        'updated_at' => $time,
                    ];
                })
                ->chunk(1000)
                ->each(function ($chunk) {
                    TeamCluster::insert($chunk->all());
                });

            $bar->advance();
        }

        $bar->finish();

        $this->info("\nClusters finished...");
    }

    /**
     * @param Team $team
     */
    protected function deleteClusters(Team $team)
    {
        $this->info("Deleting clusters...");

        TeamCluster::whereTeamId($team->id)->delete();
    }
}
