<?php

namespace Tests\Feature;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use Intervention\Image\Facades\Image;
use Tests\Doubles\Actions\Locations\FakeReverseGeocodingAction;

trait HasPhotoUploads
{
    protected string $imagePath;
    private string $imageDisplayName = '10735, Carlisle Pike, Latimore Township, Adams County, Pennsylvania, 17324, USA';
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

    protected FakeReverseGeocodingAction |null $geocodingAction = null;

    protected function setImagePath (): void
    {
        $this->imagePath = storage_path('framework/testing/1x1.jpg');

        $this->setMockForGeocodingAction();
    }

    protected function getImageAndAttributes ($mimeType = 'jpg', $withAddress = []): array
    {
        // $image = file_get_contents($this->imagePath);
        $image = Image::canvas(1, 1, '#'.dechex(mt_rand(0, 0xFFFFFF)));
        $file = UploadedFile::fake()->create("image.$mimeType", 100, $mimeType);

        $latitude = 40.053030045789;
        $longitude = -77.15449870066;
        $geoHash = 'dr15u73vccgyzbs9w4uj';
        $displayName = $this->imageDisplayName;

        if (!empty($withAddress)) {
            $this->address = $withAddress;
            $this->setMockForGeocodingAction();
        }
        $this->geocodingAction->withAddress($this->address);
        \Log::info('Geocoding address used:', $this->address);

        $address = $this->address;

        $dateTime = now()->addSeconds(rand(1, 999)); // carbon instance
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;
        $formattedDateTime = $dateTime->format('Y:m:d H:i:s'); // string

        $filepath = "$year/$month/$day/{$file->hashName()}";
        $imageName = Storage::disk('s3')->url($filepath);
        $bboxImageName = Storage::disk('bbox')->url($filepath);

        return compact(
            'latitude', 'longitude', 'geoHash', 'displayName', 'address',
            'dateTime', 'filepath', 'file', 'imageName', 'bboxImageName',
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

    protected function getCountryId (): int
    {
        return Country::where('shortcode', $this->address['country_code'])->first()->id;
    }

    protected function getStateId (): int
    {
        return State::where('state', $this->address['state'])->first()->id;
    }

    protected function getCityId (): int
    {
        return City::where(['city' => $this->address['city']])->first()->id;
    }

    protected function setMockForGeocodingAction (): void
    {
        $this->geocodingAction = (new FakeReverseGeocodingAction())->withAddress($this->address);
        $this->swap(ReverseGeocodeLocationAction::class, $this->geocodingAction);
    }
}
