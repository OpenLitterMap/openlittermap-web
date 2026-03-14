---
name: clustering-system
description: ClusteringService, tile keys, dirty tiles/teams, clustering commands, ClusterController GeoJSON API, PhotoObserver dirty marking, and map cluster rendering.
---

# Clustering System

Hierarchical grid-based clustering for map visualization. Photos are grouped into clusters at 9 zoom levels (0, 2, 4, 6, 8, 10, 12, 14, 16) using a two-tier strategy:

- **Global (zoom 0-6):** Single query across all verified photos (`verified >= 2`)
- **Per-tile (zoom 8-16):** Uses pre-computed tile keys and generated columns for performance

Team clustering is unified into the same `clusters` table via `team_id` column (0 = global, N = team-specific).

## Key Files

- `config/clustering.php` — Grid sizes, zoom levels, tile size, TTL, limits
- `app/Services/Clustering/ClusteringService.php` — Core clustering logic (raw SQL, not Eloquent)
- `app/Http/Controllers/Clusters/ClusterController.php` — Public API endpoint (GeoJSON + ETag)
- `app/Http/Controllers/Teams/TeamsClusterController.php` — Team cluster API (GeoJSON + bbox). `points()` selects `summary` (not `result_string`).
- `app/Observers/PhotoObserver.php` — Dirty tile/team marking + school privacy
- `app/Console/Commands/Clusters/UpdateClusters.php` — `clustering:update` (full rebuild)
- `app/Console/Commands/Clusters/ProcessDirtyTiles.php` — `clustering:process-dirty` (incremental)
- `app/Console/Commands/Clusters/CheckMigrationStatus.php` — `clustering:check-migration` (diagnostic)
- `app/Models/Cluster.php` — Eloquent model (composite PK, `$timestamps = false`)
- `resources/js/stores/maps/clusters/index.js` — Pinia store for cluster data
- `resources/js/stores/maps/points/requests.js` — Points store: `GET_POINTS()` with page, year, date, username, signal params
- `resources/js/views/Maps/helpers/clustersHelper.js` — Frontend cluster rendering + interactions
- `resources/js/views/Maps/helpers/mapLifecycleHelper.js` — Map init (always adds cluster layer), cleanup, health checks
- `resources/js/views/Maps/helpers/pointsHelper.js` — Points view, pagination, stats, abort signals
- `tests/Feature/Map/Clusters/ClusteringTest.php` — Core clustering tests
- `tests/Feature/Map/Clusters/ClusteringApiTest.php` — API endpoint tests
- `tests/Feature/Map/Clusters/TeamClusteringTest.php` — Team clustering tests

## Artisan Commands

```bash
# Full rebuild
clustering:update --populate       # Backfill NULL tile_keys (50k chunks)
clustering:update --all            # Recluster all global + tile zoom levels
clustering:update --team=5         # Cluster a specific team
clustering:update --all-teams      # Cluster all teams with photos
clustering:update --stats          # Show statistics + integrity check
clustering:update --explain        # Show query execution plan (combine with --all)

# Interactive menu (no flags)
clustering:update                  # Shows choice() menu: all, populate, both, team, all-teams, stats

# Incremental (scheduled every 5 minutes)
clustering:process-dirty           # Process dirty tiles + teams
clustering:process-dirty --limit=100 --team-limit=20

# Diagnostic
clustering:check-migration         # Verify columns, indexes, PK, data integrity
```

**Scheduler** (`Kernel.php`): `clustering:process-dirty` runs every 5 minutes. `clustering:update --all --all-teams` runs nightly at 00:10.

## Invariants

1. **Global clustering uses `verified >= 2`.** ADMIN_APPROVED and above. Team clustering uses `verified >= 1` (tagged, so school students see their uploads on the team map before teacher approval).
2. **PhotoObserver uses two thresholds.** Global tile dirty: `>= ADMIN_APPROVED`. Team dirty: `>= VERIFIED`.
3. **ClusteringService uses raw SQL, not Eloquent.** All clustering queries use `DB::statement()` with `INSERT...SELECT`. The Cluster model exists but is not used by the pipeline.
4. **Team clusters are in the same table as global clusters.** `team_id = 0` for global, `team_id = N` for team-specific. All global queries MUST filter `WHERE team_id = 0`.
5. **`tile_key` must be populated before per-tile clustering works.** Always run `--populate` before `--all`.
6. **Global sentinel tile key is `4294967295`** (UINT max). Global zoom clusters use this value. Per-tile clusters use the actual tile key.

## Architecture

```
Photo saved/deleted
  → PhotoObserver marks tile dirty (if verified >= ADMIN_APPROVED)
  → PhotoObserver marks team dirty (if verified >= VERIFIED and has team_id)
    → clustering:process-dirty (scheduler, every 5 min)
      → ClusteringService::clusterTile()    — one tile across zooms 8-16
      → ClusteringService::clusterTeam()    — one team across zooms 0-16

Nightly full rebuild:
  → clustering:update --all --all-teams
```

## Tile Key Computation

Formula with 0.25° tile size (1440 x 720 grid):
```
latIndex = FLOOR((lat + 90) / 0.25)
lonIndex = FLOOR((lon + 180) / 0.25)
tileKey  = latIndex * 1440 + lonIndex
```

## Database Schema

### Clusters table (composite PK)
```sql
PRIMARY KEY (team_id, tile_key, zoom, year, cell_x, cell_y)
team_id       UNSIGNED INT      -- 0 = global, N = team-specific
tile_key      UNSIGNED INT      -- 4294967295 = global sentinel
zoom          INT               -- 0-16
year          SMALLINT UNSIGNED -- 0 = all-time
cell_x, cell_y INT              -- Grid cell coordinates
lat, lon      DOUBLE            -- Centroid
point_count   BIGINT UNSIGNED   -- Photos in cluster
grid_size     DECIMAL(6,3)
```

### Dirty tiles table
```sql
dirty_tiles: tile_key (PK), changed_at, attempts
```

Uses upsert with backoff: after 3 attempts, `changed_at` advances by 5 minutes. Auto-cleaned after 24 hours.

**Note:** `dirty_teams` table was dropped (2026-03-14). Team clustering is now on-demand only via `clustering:update --team=ID` or `--all-teams`.

## API Endpoints

- `GET /api/clusters` — Public. Params: `zoom`, `bbox[]`, `lat`, `lon`. Returns GeoJSON FeatureCollection with ETag caching (304 support). Limit 5,000 clusters.
- `GET /api/clusters/zoom-levels` — Available zoom configurations.
- `GET /api/teams/clusters/{team}` — Auth required. Same bbox filtering and GeoJSON format.

## Common Mistakes

- **Using `verified = 2` instead of `verified >= 2`.** Photos at BBOX_APPLIED (3), BBOX_VERIFIED (4), AI_READY (5) must be included.
- **Forgetting `team_id = 0` in global queries.** Without this, team clusters leak into public map data.
- **Running `--all` without `--populate` first.** Photos without `tile_key` are excluded from per-tile clustering.
- **Assuming the Cluster model is used by the pipeline.** ClusteringService uses raw SQL for performance.
- **Scheduling deleted commands.** The old `clusters:generate-all` and `clusters:generate-team-clusters` are deleted. Use `clustering:update` and `clustering:process-dirty`.
- **Not flushing cluster cache after regeneration.** `clustering:update --all` and `--all-teams` auto-flush `clusters:v5:*` cache keys. Cache prefix has NO colon separator: `openlittermap_cacheclusters:v5:*`.
- **Conditionally adding cluster layer to map.** `mapLifecycleHelper.js` ALWAYS adds the clusters GeoJSON layer to the map instance, even when initial fetch returns 0 features. Without this, subsequent cluster loads after panning/zooming don't render.
- **Ignoring params in `GET_POINTS()`.** The store method must destructure and pass `page`, `year`, `fromDate`, `toDate`, `username`, `signal` to the backend. The API returns pagination as `page` (not `current_page`) at root level — `pointsHelper.getPaginationData()` normalizes this.
