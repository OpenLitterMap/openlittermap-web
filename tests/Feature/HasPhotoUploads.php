<?php

namespace Tests\Feature;

use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Doubles\Actions\Locations\FakeReverseGeocodingAction;

trait HasPhotoUploads
{
    protected $imagePath;
    private $imageDisplayName = '10735, Carlisle Pike, Latimore Township,' .
    ' Adams County, Pennsylvania, 17324, USA';
    private $address = [
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
    /** @var FakeReverseGeocodingAction */
    protected $geocodingAction = null;

    protected function setImagePath()
    {
        $this->imagePath = storage_path('framework/testing/1x1.jpg');

        $this->setMockForGeocodingAction();
    }

    protected function getImageAndAttributes($mimeType = 'jpg', $withAddress = []): array
    {
        $exifImage = file_get_contents($this->imagePath);
        $file = UploadedFile::fake()->createWithContent(
            'image.' . $mimeType,
            $exifImage
        );
        $latitude = 40.053030045789;
        $longitude = -77.15449870066;
        $geoHash = 'dr15u73vccgyzbs9w4uj';
        $displayName = $this->imageDisplayName;
        $this->address = array_merge($this->address, $withAddress);
        $address = $this->address;

        $dateTime = now();
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;

        $filepath = "$year/$month/$day/{$file->hashName()}";
        $imageName = Storage::disk('s3')->url($filepath);
        $bboxImageName = Storage::disk('bbox')->url($filepath);

        return compact(
            'latitude', 'longitude', 'geoHash', 'displayName', 'address',
            'dateTime', 'filepath', 'file', 'imageName', 'bboxImageName'
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

    protected function getCountryId(): int
    {
        return Country::where('shortcode', $this->address['country_code'])->first()->id;
    }

    protected function getStateId(): int
    {
        return State::where('state', $this->address['state'])->first()->id;
    }

    protected function getCityId(): int
    {
        return City::where(['city' => $this->address['city']])->first()->id;
    }

    protected function setMockForGeocodingAction()
    {
        $this->geocodingAction = new FakeReverseGeocodingAction();
        $this->swap(ReverseGeocodeLocationAction::class, $this->geocodingAction);
    }
}
