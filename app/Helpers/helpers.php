<?php

use Carbon\Carbon;
use App\Models\Photo;
use App\Actions\LogAdminVerificationAction;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\Redis;

if (!function_exists('array_diff_assoc_recursive'))
{
    /**
     * Computes the difference of arrays with additional index check, recursively.
     * @see https://www.php.net/manual/en/function.array-diff-assoc.php#111675
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    function array_diff_assoc_recursive (array $array1, array $array2): array
    {
        $difference = [];
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }
}

if (!function_exists('logAdminAction'))
{
    /**
     * Logs the admin action into the database
     * for storing xp updates on the photo's user
     * @param Photo $photo
     * @param string $action
     * @param array|null $tagsDiff
     * @return void
     */
    function logAdminAction (Photo $photo, string $action, array $tagsDiff = null): void
    {
        /** @var LogAdminVerificationAction $action */
        $logger = app(LogAdminVerificationAction::class);

        $logger->run(
            auth()->user(),
            $photo,
            $action,
            $tagsDiff['added'] ?? [],
            $tagsDiff['removed'] ?? [],
            $tagsDiff['rewardedAdminXp'] ?? 0,
            $tagsDiff['removedUserXp'] ?? 0
        );
    }
}

if (!function_exists('rewardXpToAdmin'))
{
    /**
     * Rewards the admin performing the verification with xp
     *
     * @param int $xp
     * @return void
     */
    function rewardXpToAdmin (int $xp = 1): void
    {
        auth()->user()->increment('xp', $xp);

        $userId = (string) auth()->id();
        $userScope = RedisKeys::user(auth()->id());

        Redis::pipeline(function ($pipe) use ($xp, $userId, $userScope) {
            $pipe->zIncrBy(RedisKeys::xpRanking(RedisKeys::global()), $xp, $userId);
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', $xp);
        });
    }
}

if (!function_exists('sort_ppm'))
{
    /**
     * Sort an array of photos_per_month dates
     */
    function sort_ppm ($array)
    {
        return collect($array)->sortBy(function ($value, $key) {
            // Key is in the format mm-yy: 07-22
            // Add 1st date of the month to the YY-MM element
            // 01-mm-yy
            return Carbon::createFromFormat(
                "d-m-y",
                "01-" . $key
            )->unix();
        });
    }
}

if (!function_exists('getDateTimeForPhoto'))
{
    /**
     * Get the DateTime for the photo
     * @param array $exif
     * @return Carbon|null
     */
    function getDateTimeForPhoto (array $exif): ?Carbon
    {
        $dateTime = $exif['DateTimeOriginal'] ?? null;

        if (!$dateTime) {
            $dateTime = $exif['DateTime'] ?? null;
        }

        if (!$dateTime && isset($exif['FileDateTime'])) {
            $dateTime = $exif['FileDateTime'];
        }

        if (!$dateTime) {
            \Log::warning('DateTime not found in EXIF data', [
                'user_id' => auth()->id(),
                'exif' => $exif
            ]);

            // send email to admin to check exif data

            return null;
        }

        return Carbon::parse($dateTime);
    }
}

if (!function_exists('getCoordinatesFromExif'))
{
    function getCoordinatesFromPhoto (array $exif): ?array
    {
        $lat_ref   = $exif["GPSLatitudeRef"];
        $lat       = $exif["GPSLatitude"];
        $long_ref  = $exif["GPSLongitudeRef"];
        $lon       = $exif["GPSLongitude"];

        return dmsToDec($lat, $lon, $lat_ref, $long_ref);
    }
}

if (!function_exists('dmsToDec'))
{
    /**
     * Convert Degrees, Minutes and Seconds to Lat, Long
     * Cheers to Hassan for this!
     *
     *  "GPSLatitude" => array:3 [
            0 => "51/1"
            1 => "50/1"
            2 => "888061/1000000"
        ]
     */
    function dmsToDec ($lat, $lon, $lat_ref, $long_ref): ?array
    {
        $lat[0] = explode("/", $lat[0]);
        $lat[1] = explode("/", $lat[1]);
        $lat[2] = explode("/", $lat[2]);

        $lon[0] = explode("/", $lon[0]);
        $lon[1] = explode("/", $lon[1]);
        $lon[2] = explode("/", $lon[2]);

        $lat[0] = (int)$lat[0][0] / (int)$lat[0][1];
        $lon[0] = (int)$lon[0][0] / (int)$lon[0][1];

        $lat[1] = (int)$lat[1][0] / (int)$lat[1][1];
        $lon[1] = (int)$lon[1][0] / (int)$lon[1][1];

        $lat[2] = (int)$lat[2][0] / (int)$lat[2][1];
        $lon[2] = (int)$lon[2][0] / (int)$lon[2][1];

        $lat = $lat[0]+((($lat[1]*60)+($lat[2]))/3600);
        $lon = $lon[0]+((($lon[1]*60)+($lon[2]))/3600);

        if ($lat_ref === "S") $lat = $lat * -1;
        if ($long_ref === "W") $lon = $lon * -1;

        return [$lat, $lon];
    }
}

