<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    // ─── getDateTimeForPhoto ──────────────────────────────

    public function test_datetime_extracted_from_date_time_original(): void
    {
        $exif = ['DateTimeOriginal' => '2025:06:15 14:30:00'];

        $result = getDateTimeForPhoto($exif);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(6, $result->month);
    }

    public function test_datetime_falls_back_to_date_time(): void
    {
        $exif = ['DateTime' => '2025:01:01 12:00:00'];

        $result = getDateTimeForPhoto($exif);

        $this->assertInstanceOf(Carbon::class, $result);
    }

    public function test_datetime_falls_back_to_file_date_time(): void
    {
        $exif = ['FileDateTime' => 1700000000];

        $result = getDateTimeForPhoto($exif);

        $this->assertInstanceOf(Carbon::class, $result);
    }

    public function test_datetime_returns_null_when_no_exif_datetime(): void
    {
        $exif = ['Model' => 'iPhone 15', 'Make' => 'Apple'];

        $result = getDateTimeForPhoto($exif);

        $this->assertNull($result);
    }

    // ─── dmsToDec ─────────────────────────────────────────

    public function test_dms_to_dec_converts_valid_coordinates(): void
    {
        // Dublin: 53°20'59" N, 6°15'37" W
        $lat = ['53/1', '20/1', '59000000/1000000'];
        $lon = ['6/1', '15/1', '37000000/1000000'];

        $result = dmsToDec($lat, $lon, 'N', 'W');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(53.349, $result[0], 0.01);
        $this->assertEqualsWithDelta(-6.260, $result[1], 0.01);
    }

    public function test_dms_to_dec_returns_null_on_zero_denominator(): void
    {
        $lat = ['51/1', '50/0', '888061/1000000'];
        $lon = ['6/1', '15/1', '37000000/1000000'];

        $result = dmsToDec($lat, $lon, 'N', 'W');

        $this->assertNull($result);
    }

    public function test_dms_to_dec_returns_null_on_zero_denominator_in_seconds(): void
    {
        $lat = ['51/1', '50/1', '888061/0'];
        $lon = ['6/1', '15/1', '37000000/1000000'];

        $result = dmsToDec($lat, $lon, 'N', 'W');

        $this->assertNull($result);
    }

    public function test_dms_to_dec_returns_null_on_zero_denominator_in_lon(): void
    {
        $lat = ['51/1', '50/1', '888061/1000000'];
        $lon = ['6/0', '15/1', '37000000/1000000'];

        $result = dmsToDec($lat, $lon, 'N', 'W');

        $this->assertNull($result);
    }

    public function test_dms_to_dec_handles_southern_hemisphere(): void
    {
        // Cape Town: approx 33°55' S, 18°25' E
        $lat = ['33/1', '55/1', '0/1'];
        $lon = ['18/1', '25/1', '0/1'];

        $result = dmsToDec($lat, $lon, 'S', 'E');

        $this->assertNotNull($result);
        $this->assertLessThan(0, $result[0]); // negative latitude
        $this->assertGreaterThan(0, $result[1]); // positive longitude
    }

    public function test_dms_to_dec_accepts_zero_zero_coordinates(): void
    {
        // Gulf of Guinea — 0,0 is a valid location
        $lat = ['0/1', '0/1', '0/1'];
        $lon = ['0/1', '0/1', '0/1'];

        $result = dmsToDec($lat, $lon, 'N', 'E');

        $this->assertNotNull($result);
        $this->assertEquals(0.0, $result[0]);
        $this->assertEquals(0.0, $result[1]);
    }

    // ─── getCoordinatesFromPhoto ──────────────────────────

    public function test_get_coordinates_from_photo_returns_null_on_bad_data(): void
    {
        $exif = [
            'GPSLatitudeRef' => 'N',
            'GPSLatitude' => ['51/1', '50/0', '0/1'], // zero denominator in minutes
            'GPSLongitudeRef' => 'W',
            'GPSLongitude' => ['6/1', '15/1', '0/1'],
        ];

        $result = getCoordinatesFromPhoto($exif);

        $this->assertNull($result);
    }
}
