<?php


namespace Tests\Feature;


use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Http\UploadedFile;

trait HasPhotoUploads
{
    protected $imagePath;

    protected function setImagePath()
    {
        $this->imagePath = storage_path('framework/testing/1x1.jpg');
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
        $displayName = '10735, Carlisle Pike, Latimore Township,' .
            ' Adams County, Pennsylvania, 17324, USA';
        $address = [
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

        $dateTime = now();
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;

        $localUploadsPath = "/local-uploads/$year/$month/$day/{$file->hashName()}";
        $filepath = public_path($localUploadsPath);
        $imageName = config('app.url') . $localUploadsPath;
        $productionImageName = "$year/$month/$day/{$file->hashName()}";

        return compact(
            'latitude', 'longitude', 'geoHash', 'displayName', 'address',
            'dateTime', 'filepath', 'file', 'imageName', 'productionImageName'
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
}
