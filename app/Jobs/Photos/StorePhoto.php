<?php

namespace App\Jobs\Photos;

use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Helpers\Post\UploadHelper;
use App\Models\User\User;
use Carbon\Carbon;
use GeoHash;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StorePhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    private $userId;
    /**
     * @var Carbon
     */
    private $dateTime;
    /**
     * @var array
     */
    private $exif;
    /**
     * @var string
     */
    private $imageName;
    /**
     * @var string
     */
    private $bboxImageName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        Carbon $dateTime,
        int $userId,
        array $exif,
        string $imageName,
        string $bboxImageName
    )
    {
        $this->userId = $userId;
        $this->dateTime = $dateTime;
        $this->exif = $exif;
        $this->imageName = $imageName;
        $this->bboxImageName = $bboxImageName;
    }

    /**
     * Get the middleware the job should pass through.
     * If the job fails once, we will wait 1 hour before retrying
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(1))->backoff(60)
        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws GuzzleException
     */
    public function handle()
    {
        $user = User::find($this->userId);

        // Get phone model
        $model = (array_key_exists('Model', $this->exif) && !empty($this->exif["Model"]))
            ? $this->exif["Model"]
            : 'Unknown';

        // Get coordinates
        $lat_ref = $this->exif["GPSLatitudeRef"];
        $lat = $this->exif["GPSLatitude"];
        $long_ref = $this->exif["GPSLongitudeRef"];
        $long = $this->exif["GPSLongitude"];

        $latLong = $this->dmsToDec($lat, $long, $lat_ref, $long_ref);
        $latitude = $latLong[0];
        $longitude = $latLong[1];

        /** @var ReverseGeocodeLocationAction $action */
        $action = app(ReverseGeocodeLocationAction::class);
        $revGeoCode = $action->run($latitude, $longitude);

        // The entire address as a string
        $displayName = $revGeoCode["display_name"];

        // Extract the address array
        $addressArray = $revGeoCode["address"];
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        // todo- check all locations for "/" and replace with "-"
        /** @var UploadHelper $helper */
        $helper = app(UploadHelper::class);
        $country = $helper->getCountryFromAddressArray($addressArray);
        $state = $helper->getStateFromAddressArray($country, $addressArray);
        $city = $helper->getCityFromAddressArray($country, $state, $addressArray);

        $geoHash = GeoHash::encode($latLong[0], $latLong[1]);

        $user->photos()->create([
            'filename' => $this->imageName,
            'datetime' => $this->dateTime,
            'lat' => $latLong[0],
            'lon' => $latLong[1],
            'display_name' => $displayName,
            'location' => $location,
            'road' => $road,
            'city' => $city->city,
            'county' => $state->state,
            'country' => $country->country,
            'country_code' => $country->shortcode,
            'model' => $model,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'platform' => 'web',
            'geohash' => $geoHash,
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $this->bboxImageName,
            'address_array' => json_encode($addressArray)
        ]);

        // $user->images_remaining -= 1;

        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update([
            'xp' => DB::raw('ifnull(xp, 0) + 1'),
            'total_images' => DB::raw('ifnull(total_images, 0) + 1'),
            'has_uploaded' => true
        ]);

        $user->refresh();

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        // Broadcast this event to anyone viewing the global map
        // This will also update country, state, and city.total_contributors_redis
        event(new ImageUploaded(
            $city->city,
            $state->state,
            $country->country,
            $country->shortcode,
            $this->imageName,
            $teamName,
            $user->id,
            $country->id,
            $state->id,
            $city->id,
            $user->is_trusted,
            $user->active_team
        ));

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        event(new IncrementPhotoMonth(
            $country->id,
            $state->id,
            $city->id,
            $this->dateTime
        ));
    }

    /**
     * Convert Degrees, Minutes and Seconds to Lat, Long
     * Cheers to Hassan for this!
     *
     *  "GPSLatitude" => array:3 [ might be an array
     * 0 => "51/1"
     * 1 => "50/1"
     * 2 => "888061/1000000"
     * ]
     */
    private function dmsToDec($lat, $long, $latRef, $longRef): array
    {
        $lat[0] = explode("/", $lat[0]);
        $lat[1] = explode("/", $lat[1]);
        $lat[2] = explode("/", $lat[2]);

        $long[0] = explode("/", $long[0]);
        $long[1] = explode("/", $long[1]);
        $long[2] = explode("/", $long[2]);

        $lat[0] = (int) $lat[0][0] / (int) $lat[0][1];
        $long[0] = (int) $long[0][0] / (int) $long[0][1];

        $lat[1] = (int) $lat[1][0] / (int) $lat[1][1];
        $long[1] = (int) $long[1][0] / (int) $long[1][1];

        $lat[2] = (int) $lat[2][0] / (int) $lat[2][1];
        $long[2] = (int) $long[2][0] / (int) $long[2][1];

        $lat = $lat[0] + ((($lat[1] * 60) + ($lat[2])) / 3600);
        $long = $long[0] + ((($long[1] * 60) + ($long[2])) / 3600);

        if ($latRef === "S") $lat = $lat * -1;
        if ($longRef === "W") $long = $long * -1;

        return [$lat, $long];
    }

}
