<?php

namespace Tests\Feature;

use App\Models\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use Tests\Doubles\Actions\Locations\FakeReverseGeocodingAction;
use Tests\Support\TestLocationService;

trait HasPhotoUploads
{
    protected TestLocationService $locationService;
    protected FakeReverseGeocodingAction|null $geocodingAction = null;

    protected function setUpPhotoUploads(): void
    {
        $this->locationService = new TestLocationService();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setMockForGeocodingAction();
    }

    protected function setImagePath (): void
    {
        $this->setMockForGeocodingAction();
    }

    private array $address = [
        "house_number" => "10735",
        "road" => "Carlisle Pike",
        "city" => "Latimore Township",
        "county" => "Adams County",
        "state" => "Pennsylvania",
        "postcode" => "17324",
        "country" => "United States of America",
        "country_code" => "us",
        "suburb" => "unknown"
    ];
    private string $imageDisplayName = '10735, Carlisle Pike, Latimore Township, Adams County, Pennsylvania, 17324, USA';


    protected function getImageAndAttributes ($mimeType = 'jpg', $withAddress = []): array
    {
        $file = new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'image_with_exif.JPG',
            'image/jpeg',
            null,
            true // test mode
        );

        $latitude = 40.053030045789;
        $longitude = -77.15449870066;
        $geoHash = 'dr15u73vccgyzbs9w4uj';
        $displayName = $this->imageDisplayName;

        if (!empty($withAddress)) {
            $this->address = $withAddress;
            $this->setMockForGeocodingAction();
        }

        $this->geocodingAction->withAddress($this->address);

        $address = $this->address;

        $dateTime = now()->addSeconds(rand(1, 999)); // carbon instance
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;
        $formattedDateTime = $dateTime->format('Y:m:d H:i:s'); // string

        $filepath = "$year/$month/$day/{$file->hashName()}";

        Storage::disk('s3')->put($filepath, file_get_contents($file->getRealPath()));
        Storage::disk('bbox')->put($filepath, file_get_contents($file->getRealPath()));
        $fullFilePath = Storage::disk('s3')->url($filepath);
        $fullBBoxFilePath = Storage::disk('bbox')->url($filepath);

        return compact(
            'latitude', 'longitude', 'geoHash', 'displayName', 'address',
            'dateTime', 'filepath', 'file', 'fullFilePath', 'fullBBoxFilePath',
            'formattedDateTime',
        );
    }

    protected function getApiImageAttributes (array $imageAttributes): array
    {
        return [
            'photo' => $imageAttributes['file'],
            'lat' => $imageAttributes['latitude'],
            'lon' => $imageAttributes['longitude'],
            'date' => $imageAttributes['dateTime'],
            'model' => 'test model',
            'picked_up' => true
        ];
    }

    protected function setMockForGeocodingAction (): void
    {
        $this->geocodingAction = (new FakeReverseGeocodingAction())->withAddress($this->address);
        $this->swap(ReverseGeocodeLocationAction::class, $this->geocodingAction);
    }

    protected function createPhotoFromImageAttributes(array $attributes, User $user): \App\Models\Photo
    {
        $locationData = $this->locationService->createOrGetLocationFromAddress($attributes['address']);

        return $user->photos()->create([
            'verified' => 0,
            'filename' => $attributes['fullFilePath'],
            'five_hundred_square_filepath' => $attributes['fullBBoxFilePath'],
            'datetime' => $attributes['dateTime'],
            'lat' => $attributes['latitude'],
            'lon' => $attributes['longitude'],
            'display_name' => $attributes['displayName'],
            'location' => $attributes['address']['house_number'],
            'road' => $attributes['address']['road'],
            'country_id' => $locationData['country_id'],
            'state_id' => $locationData['state_id'],
            'city_id' => $locationData['city_id'],
            'city' => $attributes['address']['city'],
            'county' => $attributes['address']['state'],
            'country' => $attributes['address']['country'],
            'country_code' => $attributes['address']['country_code'],
            'model' => 'test model',
            'platform' => 'mobile',
            'geohash' => $attributes['geoHash'],
            'team_id' => $user->active_team,
            'address_array' => json_encode($attributes['address']),
        ]);
    }
}
