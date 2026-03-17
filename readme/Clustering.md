# OpenLitterMap v5 — Clustering System

## Overview

Hierarchical grid-based clustering for map visualization. Photos are grouped into clusters at 9 zoom levels (0, 2, 4, 6, 8, 10, 12, 14, 16) using a two-tier strategy:

- **Global (zoom 0-6):** Single query across all verified photos
- **Per-tile (zoom 8-16):** Uses pre-computed tile keys and generated columns for performance

Team clustering is unified into the same `clusters` table via a `team_id` column (`0` = global, `N` = team-specific). Incremental updates via dirty tile + dirty team tracking. API responses use ETag caching + GeoJSON format.

---

## Migration Status

The new clustering infrastructure requires 4 migrations that have **not yet been applied** to production:

| Migration | Adds |
|-----------|------|
| `2025_06_28_create_clustering_infrastructure` | `tile_key` on photos, `tile_key`/`cell_x`/`cell_y`/`location`/`grid_size` on clusters, `dirty_tiles` table, `uk_cluster` unique key |
| `2025_07_06_add_clustering_performance_optimisations` | Generated `cell_x`/`cell_y` columns on photos (0.05 grid), `idx_photos_fast_cluster` covering index |
| `2025_07_11_update_clustering_grid_size` | Re-creates generated columns with 0.01 grid (replaces 0.05) |
| `2025_10_04_make_clusters_location_generated` | Converts `location` to a generated POINT column from lat/lon |
| `2026_02_25_add_team_id_to_clusters` | Adds `team_id` (NOT NULL DEFAULT 0) to clusters PK, `idx_team_zoom` index |
| `2026_02_25_drop_team_clusters_table` | Drops legacy `team_clusters` table |
| `2026_02_25_create_dirty_teams_table` | `dirty_teams` table for incremental team reclustering |

**Current state:** The `clusters` table still has the legacy schema (`id` PK, `geohash`, `point_count_abbreviated`) on production. The `ClusterController.index()` endpoint works with both schemas (reads `lat`, `lon`, `point_count`, `zoom`). The new `ClusteringService` methods require the migrations to be applied.

**After migration:** The `CheckMigrationStatus` command detects legacy columns (`id`, `geohash`, `point_count_abbreviated`, `created_at`) and warns if composite PK migration is incomplete.

---

## Architecture

```
Photo saved/deleted
  → PhotoObserver marks tile dirty (if verified >= ADMIN_APPROVED)
  → PhotoObserver marks team dirty (if verified >= VERIFIED and has team_id)
    → clustering:process-dirty (scheduler or manual)
      → ClusteringService::clusterTile()     — Reclusters one tile across tile zooms (8-16)
      → ClusteringService::clusterTeam()     — Reclusters one team across all zooms (0-16)

Full rebuild:
  → clustering:update --populate    (backfill tile_key on photos)
  → clustering:update --all         (recluster all global zooms)
  → clustering:update --all-teams   (recluster all teams with photos)
  → clustering:update --team=5      (recluster a specific team)
```

---

## Key Files

| File | Purpose |
|------|---------|
| `config/clustering.php` | Grid sizes, zoom levels, tile size, TTL, limits |
| `app/Services/Clustering/ClusteringService.php` | Core clustering logic |
| `app/Http/Controllers/Clusters/ClusterController.php` | API endpoint (GeoJSON + ETag) |
| `app/Http/Controllers/Teams/TeamsClusterController.php` | Team cluster API (GeoJSON + bbox) |
| `app/Observers/PhotoObserver.php` | Dirty tile/team marking + school privacy |
| `app/Console/Commands/Clusters/UpdateClusters.php` | `clustering:update` command |
| `app/Console/Commands/Clusters/ProcessDirtyTiles.php` | `clustering:process-dirty` command |
| `app/Console/Commands/Clusters/CheckMigrationStatus.php` | `clustering:check-migration` command |
| `app/Models/Cluster.php` | Eloquent model (composite PK, `$timestamps = false`) |
| `app/Traits/GeoJson/CreateGeoJsonPoints.php` | GeoJSON FeatureCollection builder |
| `resources/js/stores/maps/clusters/index.js` | Pinia store for cluster data |
| `resources/js/views/Maps/helpers/clustersHelper.js` | Frontend cluster rendering + interactions |
| `resources/js/views/Maps/helpers/constants.js` | `CLUSTER_ZOOM_THRESHOLD = 17` |
| `tests/Feature/Map/Clusters/ClusteringTest.php` | Core clustering tests |
| `tests/Feature/Map/Clusters/ClusteringApiTest.php` | API endpoint tests |
| `tests/Feature/Map/Clusters/ClusteringConfigurationTest.php` | Config validation tests |
| `tests/Feature/Map/Clusters/TeamClusteringTest.php` | Team clustering tests |
| `tests/Helpers/CreateTestClusterPhotosTrait.php` | Test utilities |

### Legacy files (deleted)

| File | Status |
|------|--------|
| `app/Console/Commands/Clusters/GenerateClusters.php` | **Deleted** — Old Node.js supercluster approach |
| `app/Console/Commands/Clusters/GenerateTeamClusters.php` | **Deleted** — Old Node.js team clustering |

---

## Invariants

1. **Global clustering includes all public-ready photos.** Global queries use `WHERE verified >= 2` (ADMIN_APPROVED+). Team queries use `WHERE verified >= 1` (tagged+, so school students see their uploads on the team map before teacher approval).
2. **PhotoObserver uses two thresholds.** Global tile dirty: `>= ADMIN_APPROVED`. Team dirty: `>= VERIFIED` (tagged). This means team clusters update when a student tags a photo, but global clusters only update after admin/teacher approval.
3. **`ClusteringService` uses raw SQL, not Eloquent.** All clustering queries use `DB::statement()` with `INSERT...SELECT` for performance. The Cluster model exists but is not used by the clustering pipeline.
4. **Team clusters are in the same table as global clusters.** The `clusters` table has `team_id` (0 = global, N = team-specific). All existing global queries filter by `WHERE team_id = 0`. The old `team_clusters` table has been dropped.

---

## Database Schema

### Photos table extensions

```sql
tile_key    UNSIGNED INT    -- Which 0.25° tile the photo belongs to
cell_x      INT UNSIGNED    -- Generated: FLOOR((lon + 180) / 0.01) STORED
cell_y      INT UNSIGNED    -- Generated: FLOOR((lat + 90) / 0.01) STORED
```

Indexes: `idx_photos_fast_cluster(verified, tile_key, cell_x, cell_y, lat, lon)`, `idx_photos_tile_key(tile_key)`

### Clusters table (after migration)

```sql
-- Primary key (composite)
PRIMARY KEY (team_id, tile_key, zoom, year, cell_x, cell_y)

team_id       UNSIGNED INT      -- 0 = global clusters, N = team-specific
tile_key      UNSIGNED INT      -- 4294967295 = global sentinel, otherwise per-tile
zoom          INT               -- 0-16
year          SMALLINT UNSIGNED -- 0 = all-time
cell_x        INT               -- Grid cell X
cell_y        INT               -- Grid cell Y
lat           DOUBLE            -- Centroid latitude
lon           DOUBLE            -- Centroid longitude
location      POINT             -- Generated: ST_SRID(POINT(lon, lat), 4326) STORED
point_count   BIGINT UNSIGNED   -- Photos in this cluster
grid_size     DECIMAL(6,3)      -- Grid size used
```

Indexes: `idx_clusters_spatial` (SPATIAL on `location`), `idx_zoom_tile(zoom, tile_key)`, `idx_team_zoom(team_id, zoom)`

### Dirty tiles table

```sql
tile_key      UNSIGNED INT PRIMARY KEY
changed_at    TIMESTAMP          -- When marked dirty
attempts      UNSIGNED TINYINT   -- Retry counter (backoff after 3)
```

### Dirty teams table

```sql
team_id       UNSIGNED INT PRIMARY KEY
changed_at    TIMESTAMP          -- When marked dirty
attempts      UNSIGNED TINYINT   -- Retry counter (backoff after 3)
```

---

## Tile Key Computation

Formula with 0.25° tile size (1440 x 720 grid):

```
latIndex = FLOOR((lat + 90) / 0.25)
lonIndex = FLOOR((lon + 180) / 0.25)
tileKey = latIndex * 1440 + lonIndex
```

- One tile ~28km x 28km at equator (smaller at higher latitudes)
- Deterministic and coordinate-reversible
- Invalid coordinates return `null` (excluded from clustering)
- Boundary values clamped: `min(lat, 89.999999)`, `min(lon, 179.999999)`

---

## Zoom Level Strategy

### Grid sizes per zoom

| Zoom | Grid (deg) | ~Cell Size | Strategy | Factor |
|------|-----------|------------|----------|--------|
| 0 | 30.0 | 3,330 km | Global | — |
| 2 | 15.0 | 1,665 km | Global | — |
| 4 | 5.0 | 555 km | Global | — |
| 6 | 2.0 | 222 km | Global | — |
| 8 | 0.8 | 89 km | Per-tile | 80 |
| 10 | 0.4 | 44 km | Per-tile | 40 |
| 12 | 0.08 | 8.9 km | Per-tile | 8 |
| 14 | 0.02 | 2.2 km | Per-tile | 2 |
| 16 | 0.01 | 1.1 km | Per-tile | 1 |

Cell sizes are approximate at the equator. At higher latitudes, east-west distances shrink proportionally.

### Global clustering (zoom 0-6)

`ClusteringService::clusterGlobal(int $zoom)`:
1. Delete existing clusters for this zoom
2. Query all verified photos (`WHERE verified >= 2`)
3. Group by `FLOOR((lon+180)/gridSize)`, `FLOOR((lat+90)/gridSize)`
4. Insert with `tile_key = 4294967295` (global sentinel)

### Per-tile clustering (zoom 8-16)

`ClusteringService::clusterAllTilesForZoom(int $zoom)`:
1. Delete existing tile clusters for this zoom (excludes global sentinel)
2. Uses generated columns (`cell_x`, `cell_y`) with factor division: `FLOOR(cell_x / factor)`
3. Factor = `gridSize / smallestGrid` (e.g., zoom 8: `0.8 / 0.01 = 80`)
4. Single SQL query groups all tiles at once: `GROUP BY tile_key, cluster_x, cluster_y`

### Single-tile clustering

`ClusteringService::clusterTile(int $tileKey)`:
- Reclusters one tile across all tile zooms (8-16)
- Used by dirty tile processor for incremental updates
- Skips if `tileKey` equals global sentinel

### Team clustering

`ClusteringService::clusterTeam(int $teamId)`:
1. Delete all existing clusters for this team (`WHERE team_id = $teamId`)
2. For global zooms (0-6): compute cells from lat/lon, filter photos by `team_id` and `verified >= 1`
3. For tile zooms (8-16): use generated cell columns with factor division, same team/verified filter
4. All inserted rows get `team_id = $teamId`

Key differences from global clustering:
- Uses `verified >= 1` (includes tagged-but-unapproved photos, important for school teams)
- Scoped to one team's photos
- Covers all zoom levels (0-16) in one call — team datasets are small enough for this
- No USE INDEX hint — team photo counts are small

### Hierarchical clustering (experimental, unused)

`ClusteringService::clusterHierarchical(int $fromZoom, int $toZoom)`:
- Generates zoom N+1 clusters from zoom N clusters (faster for deep zooms)
- Not called by any command. Kept for future experimentation.

---

## Dirty Tile System (Incremental Updates)

### PhotoObserver triggers

| Event | Condition | Action |
|-------|-----------|--------|
| `saving` | `verified >= ADMIN_APPROVED` + coords changed | Mark old tile dirty, compute new `tile_key` |
| `saving` | Becomes verified (`isDirty('verified')`) + no tile_key | Compute and set `tile_key` |
| `saved` | `verified >= ADMIN_APPROVED` + coords/status/tile/`is_public` changed | Mark tile dirty |
| `saved` | `verified >= VERIFIED` + has team_id + relevant fields changed | Mark team dirty |
| `deleting` | `verified >= ADMIN_APPROVED` + has tile_key | Mark tile dirty |
| `deleting` | `verified >= VERIFIED` + has team_id | Mark team dirty |

`is_public` is included in the `wasChanged` check so that toggling per-photo visibility on a verified photo immediately marks its tile dirty, ensuring the cluster counts stay accurate after the photo appears or disappears from the public map.

Team dirty marking also handles `team_id` changes — marks both old and new teams dirty.

### Dirty storage with backoff

Both `dirty_tiles` and `dirty_teams` use the same upsert pattern:

```sql
INSERT INTO dirty_tiles (tile_key, changed_at, attempts)
VALUES (?, NOW(), 0)
ON DUPLICATE KEY UPDATE
    changed_at = IF(attempts < 3, VALUES(changed_at), changed_at + INTERVAL 5 MINUTE),
    attempts = attempts + 1
```

### Processing flow (`clustering:process-dirty`)

1. **Tiles:** Fetches dirty tiles ordered by `changed_at`, limited by `--limit` (default 100). Calls `clusterTile()` for each.
2. **Teams:** Fetches dirty teams ordered by `changed_at`, limited by `--team-limit` (default 20). Calls `clusterTeam()` for each.
3. On success: deletes from dirty table
4. On failure: logs error, re-marks with backoff
5. After processing: auto-cleanup of entries with `attempts >= 3` older than TTL (24 hours)
6. Returns exit code 1 if any tiles or teams failed

---

## API Endpoints

### `GET /api/clusters` (public, no auth)

**Parameters:**
- `zoom` — Snapped to nearest configured level (rounds up)
- `bbox[]` — `[west, south, east, north]` (named keys `left/bottom/right/top`, indexed `0-3`, or comma-separated string)
- `lat`, `lon` — Center point (creates bbox from zoom if no bbox provided)

**Response:** GeoJSON FeatureCollection

```json
{
  "type": "FeatureCollection",
  "features": [{
    "type": "Feature",
    "geometry": {"type": "Point", "coordinates": [lon, lat]},
    "properties": {
      "cluster": true,
      "point_count": 42,
      "point_count_abbreviated": "42"
    }
  }]
}
```

**Headers:**
- `ETag` — md5 of `COUNT(*)|SUM(point_count)` for the zoom level
- `Cache-Control: public, max-age=300`
- `X-Cluster-Zoom` — Actual zoom level used (after snapping)
- Returns `304 Not Modified` when client `If-None-Match` matches ETag

**Edge cases:**
- Dateline crossing: `west > east` → OR condition on longitude filter
- Inverted bbox: swaps south/north automatically
- Limit: 5,000 clusters per response (configurable)
- No bbox/lat/lon: defaults to world bounds (-180, -90, 180, 90)

### `GET /api/clusters/zoom-levels` (public, no auth)

Returns available zoom level configurations.

### `GET /api/teams/clusters/{team}` (auth required)

Team-specific clusters from the unified `clusters` table (`WHERE team_id = $team`). Same bbox-based filtering and GeoJSON format as global clusters. Parameters: `zoom`, `bbox[]`.

---

## Artisan Commands

### `clustering:update`

```bash
php artisan clustering:update --populate    # Backfill NULL tile_keys (50k chunks)
php artisan clustering:update --all         # Full recluster all global zoom levels
php artisan clustering:update --team=5      # Cluster a specific team
php artisan clustering:update --all-teams   # Cluster all teams with photos
php artisan clustering:update --stats       # Show statistics + integrity check
php artisan clustering:update --explain     # Show query execution plans
```

`--populate` loops `backfillPhotoTileKeys()` until no photos remain with NULL `tile_key`. Progress bar shows count.

`--all` runs global zooms (0-6) then per-tile zooms (8-16). Outputs cluster count, time, and memory per zoom. Shows performance summary with throughput. **Automatically flushes stale `clusters:v5:*` cache keys from Redis after completion.**

`--team=N` calls `clusterTeam(N)` — reclusters all zoom levels for that team.

`--all-teams` iterates all teams with `total_images > 0` and calls `clusterTeam()` for each. **Also flushes cluster cache after completion.**

`--stats` calls `getStats()` and runs integrity check: compares verified photo count against zoom-16 cluster point_count sum. Warns on mismatch.

### `clustering:process-dirty`

```bash
php artisan clustering:process-dirty --limit=100 --team-limit=20
```

Intended for scheduler/cron. Processes dirty tiles (oldest first, `--limit` default 100) then dirty teams (`--team-limit` default 20). Reports processed/failed counts. Failed entries retry with backoff.

### `clustering:check-migration`

Validates migration state: checks for required columns/indexes on photos and clusters tables, warns about NULL tile_keys, detects legacy columns, verifies primary key structure, checks dirty_tiles table existence and backlog.

### Scheduler

`Kernel.php` runs:
- `clustering:process-dirty` — every 5 minutes (incremental)
- `clustering:update --all --all-teams` — nightly at 00:10 (full rebuild)

### Legacy commands (deleted)

- `clusters:generate-all` — **Deleted.** Was Node.js supercluster approach. Replaced by `clustering:update --all`.
- `clusters:generate-team-clusters` — **Deleted.** Was Node.js team clustering. Replaced by `clustering:update --all-teams`.

---

## Frontend Integration

### Pinia store (`resources/js/stores/maps/clusters/index.js`)

- `GET_CLUSTERS({ zoom, year, bbox, signal })` — Fetches from `/api/clusters`, supports abort signals
- `CLEAR_CLUSTERS()` — Resets state
- `hasClustersForBounds(bounds, zoom)` — Checks cache validity (0.001° tolerance)

### Cluster/points threshold

Frontend switches from clusters to individual points at zoom >= 17 (`CLUSTER_ZOOM_THRESHOLD` in `constants.js`).

### Map initialization (`mapLifecycleHelper.js`)

The clusters GeoJSON layer is ALWAYS added to the Leaflet map instance during initialization, even if the initial data fetch returns 0 features. This ensures that subsequent cluster loads (after panning/zooming) render correctly. Data is added to the layer only when features exist, but `mapInstance.addLayer(clusters)` is unconditional.

### Points pagination (`pointsHelper.js` + `points/requests.js`)

Points store `GET_POINTS()` passes `page`, `year`, `fromDate`, `toDate`, `username` and abort `signal` to the backend `/api/points` endpoint. The API returns pagination at root level (`page`, `last_page`, `total`, `has_more_pages`) — note the key is `page` not `current_page`. `pointsHelper.getPaginationData()` normalizes this to `current_page` for consistency with Vue components.

### `clustersHelper.js`

- `createClusterIcon(feature, latLng)` — Size-based icons (small < 100, medium 100-999, large 1000+)
- `onEachFeature(feature, layer, mapInstance)` — Click handler zooms in by 1 level
- `handleClusterView({...})` — Loads clusters via store, manages abort signals, clears old layers
- `shouldShowClusters(zoom)` — Returns true if zoom < 17
- `preloadAdjacentZoomLevels({...})` — Uses `requestIdleCallback` for smooth zoom transitions
- `handlePointsToClusterTransition()` / `handleClusterToPointsTransition()` — View mode switching

---

## Performance Optimizations

1. **Generated columns** — `cell_x` and `cell_y` pre-computed in MySQL as `STORED` generated columns with 0.01° precision. Factor-based division at query time avoids floating-point math in PHP.

2. **Covering index** — `idx_photos_fast_cluster(verified, tile_key, cell_x, cell_y, lat, lon)` + `USE INDEX` hints in queries. The `--explain` flag on `clustering:update` shows query plans.

3. **Single-query clustering** — Both global and per-tile clustering use one `INSERT...SELECT` per zoom level (no cursors or PHP loops over individual photos).

4. **ETag caching** — API responses cached 300s. Clients get `304 Not Modified` when data unchanged. Cache key includes zoom + bbox (4 decimal places).

5. **Batch backfill** — Tile key population uses pure SQL (`UPDATE ... SET tile_key = ... WHERE tile_key IS NULL`), chunked at 50k rows. No PHP loop over individual photos.

---

## Configuration Reference

```php
// config/clustering.php
'tile_size'               => 0.25,        // Degrees per tile (1440x720 grid)
'base_grid_deg'           => 90.0,        // Fallback grid for unconfigured zooms
'global_tile_key'         => 4294967295,  // UINT max = global sentinel
'smallest_grid'           => 0.01,        // Precision of generated cell columns
'min_cluster_size'        => 1,           // Min photos per cluster
'dirty_tile_ttl'          => 24,          // Hours before stuck tiles auto-cleaned
'cache_ttl'               => 300,         // API cache seconds
'update_chunk_size'       => 50000,       // Tile key backfill batch size
'max_clusters_per_request'=> 5000,        // API response limit
'use_spatial_index'       => true,        // Enable spatial index for queries
```

---

## Common Mistakes

- **Using `verified = 2` instead of `verified >= 2`.** Photos at BBOX_APPLIED (3), BBOX_VERIFIED (4), AI_READY (5) must be included. All clustering queries use `>= 2`.
- **Checking `verified === ADMIN_APPROVED` in observer.** Use `->value >= ADMIN_APPROVED->value` to cover all public-ready verification levels.
- **Running `clustering:update --all` without `--populate` first.** Photos without `tile_key` are excluded from per-tile clustering. Always populate first.
- **Assuming `Cluster` model is used by the pipeline.** `ClusteringService` uses raw SQL. The model exists but has `$timestamps = false` and a composite key — Eloquent save/find operations are not compatible with the legacy `id` PK until migration completes.
- **Forgetting `team_id = 0` in global queries.** All global cluster queries must include `WHERE team_id = 0` to exclude team-specific clusters. This is already done in `ClusterController` and `ClusteringService`.
- **Not flushing cluster cache after regeneration.** `clustering:update --all` and `--all-teams` now automatically flush `clusters:v5:*` cache keys. If running `ClusteringService` methods directly (e.g., from tinker), manually flush via `Redis::connection('cache')->keys($prefix . 'clusters:v5:*')` and `->del()`.
- **Cache key prefix format.** Laravel's Redis cache uses `config('cache.prefix')` with NO separator colon before the key. Actual keys look like `openlittermap_cacheclusters:v5:z2:...` (not `openlittermap_cache:clusters:v5:...`).

---

## Related Docs

| Document | Covers |
|----------|--------|
| **Upload.md** | Photo upload pipeline, PhotoObserver school privacy |
| **Metrics.md** | MetricsService (separate concern — clustering is independent of metrics) |
