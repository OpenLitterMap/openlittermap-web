<?php

namespace App\Http\Controllers\Teams;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Traits\GeoJson\CreateGeoJsonPoints;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamsClusterController extends Controller
{
    use CreateGeoJsonPoints;

    /**
     * GET /api/teams/clusters/{team}
     *
     * Returns GeoJSON FeatureCollection of clusters for a team.
     * Uses the same bbox-based approach as ClusterController.
     */
    public function clusters(Request $request, int $team): array|JsonResponse
    {
        /* ── 1. Available Zoom Levels ──────────────────────── */
        $levels = config('clustering.zoom_levels.all', [0, 2, 4, 6, 8, 10, 12, 14, 16]);

        /* ── 2. Validate Inputs ────────────────────────────── */
        $request->validate([
            'zoom' => ['nullable', 'numeric', 'between:' . min($levels) . ',' . max($levels)],
            'bbox' => ['nullable', 'array'],
        ]);

        /* ── 3. Snap Zoom ──────────────────────────────────── */
        $requested = (float) $request->input('zoom', $levels[0]);
        $zoom = collect($levels)->first(fn($z) => $z >= round($requested)) ?? end($levels);

        /* ── 4. Build Bounding Box ─────────────────────────── */
        $bbox = [-180, -90, 180, 90];

        if ($request->has('bbox')) {
            $arr = $request->input('bbox');
            if (is_array($arr)) {
                $bbox = [
                    (float) ($arr['left']   ?? $arr[0] ?? -180),
                    (float) ($arr['bottom'] ?? $arr[1] ?? -90),
                    (float) ($arr['right']  ?? $arr[2] ?? 180),
                    (float) ($arr['top']    ?? $arr[3] ?? 90),
                ];
            }
        }

        [$west, $south, $east, $north] = [
            max($bbox[0], -180),
            max($bbox[1], -90),
            min($bbox[2], 180),
            min($bbox[3], 90),
        ];

        if ($south > $north) {
            [$south, $north] = [$north, $south];
        }

        $crossesDateline = $west > $east;
        $limit = (int) config('clustering.max_clusters_per_request', 5000);

        /* ── 5. Query Clusters ─────────────────────────────── */
        $query = DB::table('clusters')
            ->select('lon', 'lat', 'point_count as count')
            ->where('team_id', $team)
            ->where('zoom', $zoom)
            ->whereBetween('lat', [$south, $north])
            ->limit($limit);

        if ($crossesDateline) {
            $query->where(function ($q) use ($west, $east) {
                $q->where('lon', '>=', $west)
                    ->orWhere('lon', '<=', $east);
            });
        } else {
            $query->whereBetween('lon', [$west, $east]);
        }

        $query->orderBy('lat')->orderBy('lon');

        $rows = $query->get();

        return $this->createGeoJsonPoints('team-clusters', $rows, true);
    }

    /**
     * GET /api/teams/points/{team}
     *
     * Returns individual photo points at deep zoom levels.
     * Uses lat/lon bounding box filtering.
     */
    public function points(Request $request, int $team): array
    {
        $user = auth()->user();

        if (! $user || ! $user->isMemberOfTeam($team)) {
            abort(403, 'Not a member of this team.');
        }

        $query = Photo::query()
            ->select(
                'id',
                'verified',
                'user_id',
                'team_id',
                'summary',
                'filename',
                'lat',
                'lon',
                'remaining',
                'datetime'
            )
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
            ])
            ->whereTeamId($team);

        // Bounding box filter using lat/lon
        if ($request->bbox && is_array($request->bbox)) {
            $bbox = $request->bbox;
            $west = (float) ($bbox['left'] ?? $bbox[0] ?? -180);
            $south = (float) ($bbox['bottom'] ?? $bbox[1] ?? -90);
            $east = (float) ($bbox['right'] ?? $bbox[2] ?? 180);
            $north = (float) ($bbox['top'] ?? $bbox[3] ?? 90);

            $query->whereBetween('lat', [$south, $north]);

            if ($west > $east) {
                // Crosses dateline
                $query->where(function ($q) use ($west, $east) {
                    $q->where('lon', '>=', $west)->orWhere('lon', '<=', $east);
                });
            } else {
                $query->whereBetween('lon', [$west, $east]);
            }
        }

        $photos = $query->limit(5000)->get();

        return $this->photosToGeojson($photos);
    }

    /**
     * Convert photos to a GeoJSON FeatureCollection for map rendering.
     */
    private function photosToGeojson($photos): array
    {
        $features = $photos->map(function (Photo $photo) {
            $name = $photo->user->show_name_maps ? $photo->user->name : null;
            $username = $photo->user->show_username_maps ? $photo->user->username : null;
            $team = $photo->team ? $photo->team->name : null;
            $filename = ($photo->user->is_trusted || $photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value)
                ? $photo->filename
                : '/assets/images/waiting.png';
            $summary = $photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value ? $photo->summary : null;

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lat, $photo->lon],
                ],
                'properties' => [
                    'photo_id' => $photo->id,
                    'summary' => $summary,
                    'filename' => $filename,
                    'datetime' => $photo->datetime,
                    'cluster' => false,
                    'verified' => $photo->verified,
                    'name' => $name,
                    'username' => $username,
                    'team' => $team,
                    'picked_up' => $photo->picked_up,
                    'social' => $photo->user->social_links,
                    'custom_tags' => $photo->customTags->pluck('tag'),
                ],
            ];
        })->toArray();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
