<?php

namespace Tests\Helpers;

use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

trait CreateTestClusterPhotos
{
    /**
     * The test user to use for photos
     */
    protected static ?User $testUser = null;

    /**
     * Get or create a test user for photos
     *
     * @return User
     */
    protected function getTestUser(): User
    {
        if (!self::$testUser) {
            // Check if a user already exists
            self::$testUser = User::first();

            if (!self::$testUser) {
                // Create a test user
                self::$testUser = User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);
            }
        }

        return self::$testUser;
    }

    /**
     * Create a photo using direct DB insert (bypasses model events)
     *
     * @param array $attributes
     * @return Photo
     */
    protected function createPhoto(array $attributes = []): Photo
    {
        // Ensure we have a valid user_id
        $userId = $attributes['user_id'] ?? $this->getTestUser()->id;

        $defaults = [
            'user_id' => $userId,
            'filename' => 'test_' . uniqid() . '.jpg',
            'model' => 'iphone',
            'datetime' => now(),
            'lat' => 51.5074,  // Default: London
            'lon' => -0.1278,
            'verified' => 2,
            'tile_key' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'remaining' => 0,
        ];

        $data = array_merge($defaults, $attributes);

        DB::table('photos')->insert($data);

        return Photo::where('filename', $data['filename'])->first();
    }

    /**
     * Create a photo with a specific user
     *
     * @param User $user
     * @param array $attributes
     * @return Photo
     */
    protected function createPhotoForUser(User $user, array $attributes = []): Photo
    {
        return $this->createPhoto(array_merge(['user_id' => $user->id], $attributes));
    }

    /**
     * Create multiple photos using direct DB insert
     *
     * @param int $count
     * @param array $attributes
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotos(int $count, array $attributes = []): \Illuminate\Support\Collection
    {
        $photos = collect();

        for ($i = 0; $i < $count; $i++) {
            $photos->push($this->createPhoto($attributes));
        }

        return $photos;
    }

    /**
     * Create photos at specific coordinates without tile_key
     *
     * @param float $lat
     * @param float $lon
     * @param int $count
     * @param array $extraAttributes
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotosAt(float $lat, float $lon, int $count = 1, array $extraAttributes = []): \Illuminate\Support\Collection
    {
        $photos = collect();

        for ($i = 0; $i < $count; $i++) {
            $attributes = array_merge([
                'lat' => $lat + (rand(-100, 100) / 100000), // Small variation
                'lon' => $lon + (rand(-100, 100) / 100000),
                'tile_key' => null,
            ], $extraAttributes);

            $photos->push($this->createPhoto($attributes));
        }

        return $photos;
    }

    /**
     * Create photos with specific tile_key for clustering tests
     *
     * @param int $tileKey
     * @param int $count
     * @param array $extraAttributes
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotosInTile(int $tileKey, int $count = 1, array $extraAttributes = []): \Illuminate\Support\Collection
    {
        $photos = collect();

        // Calculate approximate lat/lon from tile_key (0.25° grid)
        $tileX = $tileKey % 1440;
        $tileY = intval($tileKey / 1440);

        $baseLon = ($tileX * 0.25) - 180;
        $baseLat = ($tileY * 0.25) - 90;

        for ($i = 0; $i < $count; $i++) {
            $attributes = array_merge([
                'lat' => $baseLat + 0.125 + (rand(-100, 100) / 10000), // Center of tile with variation
                'lon' => $baseLon + 0.125 + (rand(-100, 100) / 10000),
                'tile_key' => $tileKey,
            ], $extraAttributes);

            $photos->push($this->createPhoto($attributes));
        }

        return $photos;
    }

    /**
     * Create unverified photos
     *
     * @param int $count
     * @param array $extraAttributes
     * @return \Illuminate\Support\Collection
     */
    protected function createUnverifiedPhotos(int $count = 1, array $extraAttributes = []): \Illuminate\Support\Collection
    {
        return $this->createPhotos($count, array_merge(['verified' => 0], $extraAttributes));
    }

    /**
     * Create photos at well-known locations
     *
     * @param string $location
     * @param int $count
     * @param bool $withTileKey Whether to set the tile_key
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotosAtLocation(string $location, int $count = 1, bool $withTileKey = false): \Illuminate\Support\Collection
    {
        $locations = [
            'london'    => ['lat' => 51.5074,  'lon' => -0.1278],
            'paris'     => ['lat' => 48.8566,  'lon' =>  2.3522],
            'new_york'  => ['lat' => 40.7128,  'lon' => -74.0060],
            'sydney'    => ['lat' => -33.8688, 'lon' => 151.2093],
            'tokyo'     => ['lat' => 35.6762,  'lon' => 139.6503],
            'dublin'    => ['lat' => 53.3498,  'lon' => -6.2603],
            'cork'      => ['lat' => 51.8985,  'lon' => -8.4756],
        ];

        $coords = $locations[strtolower($location)] ?? $locations['london'];

        if ($withTileKey) {
            // Calculate tile key for the location
            $tileKey = $this->calculateTileKey($coords['lat'], $coords['lon']);
            return $this->createPhotosInTile($tileKey, $count);
        }

        return $this->createPhotosAt($coords['lat'], $coords['lon'], $count);
    }

    /**
     * Calculate tile key from coordinates (0.25° grid)
     *
     * @param float $lat
     * @param float $lon
     * @return int
     */
    protected function calculateTileKey(float $lat, float $lon): int
    {
        $tileX = floor(($lon + 180) / 0.25);
        $tileY = floor(($lat + 90) / 0.25);
        return (int)($tileY * 1440 + $tileX);
    }

    /**
     * Create photos across multiple tiles for testing clustering
     *
     * @param array $tileCounts Array of [tileKey => count]
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotosAcrossTiles(array $tileCounts): \Illuminate\Support\Collection
    {
        $allPhotos = collect();

        foreach ($tileCounts as $tileKey => $count) {
            $photos = $this->createPhotosInTile($tileKey, $count);
            $allPhotos = $allPhotos->merge($photos);
        }

        return $allPhotos;
    }

    /**
     * Create a grid of photos for spatial testing
     *
     * @param float $centerLat
     * @param float $centerLon
     * @param int $gridSize Number of photos per side (e.g., 3 creates 3x3 grid)
     * @param float $spacing Degrees between photos
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotoGrid(float $centerLat, float $centerLon, int $gridSize = 3, float $spacing = 0.1): \Illuminate\Support\Collection
    {
        $photos = collect();
        $offset = ($gridSize - 1) * $spacing / 2;

        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $lat = $centerLat - $offset + ($y * $spacing);
                $lon = $centerLon - $offset + ($x * $spacing);

                $photos->push($this->createPhoto([
                    'lat' => $lat,
                    'lon' => $lon,
                ]));
            }
        }

        return $photos;
    }

    /**
     * Create multiple photos for multiple users
     *
     * @param array $userPhotoCounts Array of [user => photoCount]
     * @return \Illuminate\Support\Collection
     */
    protected function createPhotosForUsers(array $userPhotoCounts): \Illuminate\Support\Collection
    {
        $allPhotos = collect();

        foreach ($userPhotoCounts as $user => $count) {
            if (!($user instanceof User)) {
                $user = User::factory()->create();
            }

            $photos = $this->createPhotos($count, ['user_id' => $user->id]);
            $allPhotos = $allPhotos->merge($photos);
        }

        return $allPhotos;
    }

    /**
     * Clean up test data
     */
    protected function cleanupTestPhotos(): void
    {
        DB::table('photos')->where('filename', 'like', 'test_%')->delete();
        self::$testUser = null;
    }

    /**
     * Assert that photos have tile keys assigned
     *
     * @param \Illuminate\Support\Collection|array $photos
     * @param int|null $expectedTileKey If provided, assert specific tile key
     */
    protected function assertPhotosHaveTileKeys($photos, ?int $expectedTileKey = null): void
    {
        $photos = collect($photos);

        foreach ($photos as $photo) {
            $photo = $photo->fresh();
            $this->assertNotNull($photo->tile_key, "Photo {$photo->id} should have tile_key");

            if ($expectedTileKey !== null) {
                $this->assertEquals($expectedTileKey, $photo->tile_key,
                    "Photo {$photo->id} should have tile_key {$expectedTileKey}");
            }
        }
    }

    /**
     * Assert cluster exists at specific zoom level
     *
     * @param int $tileKey
     * @param int $zoom
     * @param array $conditions Additional conditions to check
     */
    protected function assertClusterExists(int $tileKey, int $zoom, array $conditions = []): void
    {
        $query = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('zoom', $zoom);

        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        $this->assertTrue(
            $query->exists(),
            "Cluster should exist for tile {$tileKey} at zoom {$zoom}"
        );
    }

    /**
     * Get cluster data for debugging
     *
     * @param int $tileKey
     * @return \Illuminate\Support\Collection
     */
    protected function getClustersForTile(int $tileKey): \Illuminate\Support\Collection
    {
        return DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->orderBy('zoom')
            ->get();
    }

    /**
     * Debug clustering for a tile
     *
     * @param int $tileKey
     * @return array
     */
    protected function debugClustering(int $tileKey): array
    {
        $photos = Photo::where('tile_key', $tileKey)->get();
        $clusters = DB::table('clusters')->where('tile_key', $tileKey)->get();

        return [
            'tile_key' => $tileKey,
            'photo_count' => $photos->count(),
            'verified_photos' => $photos->where('verified', 2)->count(),
            'cluster_count' => $clusters->count(),
            'clusters_by_zoom' => $clusters->groupBy('zoom')->map->count(),
            'sample_photo' => $photos->first()?->toArray(),
            'sample_cluster' => $clusters->first(),
        ];
    }
}
