<?php

use App\Actions\Locations\UpdateLeaderboardsXpAction;
use App\Actions\LogAdminVerificationAction;
use App\Models\Photo;
use Carbon\Carbon;

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

        $action = app(UpdateLeaderboardsXpAction::class);
        $action->run(auth()->id(), $xp);
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
