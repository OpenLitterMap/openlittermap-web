<?php

namespace Tests\Feature\Metrics;

use App\Models\Photo;
use App\Services\Metrics\MetricsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricsServiceXpRoundTripTest extends TestCase
{
    /**
     * Verify that XP values > 255 round-trip correctly through MetricsService.
     * Regression test: processed_xp was originally TINYINT(1) which overflows at 255.
     * Now INT UNSIGNED — this test ensures the column type is correct.
     */
    public function test_photo_with_xp_above_255_round_trips_through_metrics_service()
    {
        $xp = 500;

        $photo = Photo::factory()->create([
            'is_public' => true,
            'xp' => $xp,
            'summary' => [
                'tags' => [
                    1 => [
                        10 => ['quantity' => 200, 'materials' => [], 'brands' => [], 'custom_tags' => []],
                        11 => ['quantity' => 300, 'materials' => [], 'brands' => [], 'custom_tags' => []],
                    ],
                ],
                'totals' => [
                    'total_tags' => 500,
                    'total_objects' => 500,
                    'by_category' => [1 => 500],
                    'materials' => 0,
                    'brands' => 0,
                    'custom_tags' => 0,
                ],
            ],
        ]);

        $this->assertNull($photo->processed_at);

        app(MetricsService::class)->processPhoto($photo);

        $photo->refresh();

        $this->assertNotNull($photo->processed_at);
        // processed_xp = photo.xp + upload base (5)
        $effectiveXp = $xp + 5;
        $this->assertEquals($effectiveXp, (int) $photo->processed_xp, 'processed_xp should store XP > 255 without overflow');

        // Metrics table should also have the correct effective XP
        $globalRow = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', 0)
            ->where('location_id', 0)
            ->first();

        $this->assertNotNull($globalRow);
        $this->assertEquals($effectiveXp, (int) $globalRow->xp);
        $this->assertEquals(500, (int) $globalRow->litter);
    }

    /**
     * Verify that re-processing with same XP is idempotent (no duplicate metrics).
     */
    public function test_reprocessing_same_xp_is_idempotent()
    {
        $photo = Photo::factory()->create([
            'is_public' => true,
            'xp' => 300,
            'summary' => [
                'tags' => [
                    1 => [
                        10 => ['quantity' => 50, 'materials' => [], 'brands' => [], 'custom_tags' => []],
                    ],
                ],
                'totals' => ['total_tags' => 50, 'total_objects' => 50],
            ],
        ]);

        $service = app(MetricsService::class);

        // First process
        $service->processPhoto($photo);
        $photo->refresh();
        $firstFp = $photo->processed_fp;
        $firstXp = (int) $photo->processed_xp;

        // Second process — same data, should be a no-op
        $service->processPhoto($photo);
        $photo->refresh();

        $this->assertEquals($firstFp, $photo->processed_fp);
        $this->assertEquals($firstXp, (int) $photo->processed_xp);

        // Should still be only 1 upload in metrics
        $globalRow = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', 0)
            ->where('location_id', 0)
            ->first();

        $this->assertEquals(1, (int) $globalRow->uploads, 'Idempotent reprocess should not increment uploads');
    }
}
