<?php


namespace Tests\Feature;


use App\Actions\Photos\ReverseGeocodeLocationAction;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    protected function setImagePath()
    {
        $this->imagePath = storage_path('framework/testing/1x1.jpg');

        $this->setMockForGeocodingAction();
    }

    protected function getImageAndAttributes(): array
    {
        $exifImage = file_get_contents($this->imagePath);
        $file = UploadedFile::fake()->createWithContent(
            'image.jpg',
            $exifImage
        );
        $latitude = 40.053030045789;
        $longitude = -77.15449870066;
        $geoHash = 'dr15u73vccgyzbs9w4uj';
        $displayName = $this->imageDisplayName;
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

    protected function getCountryId(): int
    {
        return Country::where('shortcode', 'us')->first()->id;
    }

    protected function getStateId(): int
    {
        return State::where('state', 'Pennsylvania')->first()->id;
    }

    protected function getCityId(): int
    {
        return City::where(['city' => 'Latimore Township'])->first()->id;
    }

    protected function setMockForGeocodingAction()
    {
        $this->mock(ReverseGeocodeLocationAction::class)
            ->shouldReceive('run')
            ->andReturn([
                'display_name' => $this->imageDisplayName,
                'address' => $this->address
            ]);
    }
}
