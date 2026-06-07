<?php

namespace Tests\Feature\Api\Photos;

use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Actions\Photos\MakeImageAction;
use App\Models\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tests\Doubles\Actions\Locations\FakeReverseGeocodingAction;
use Tests\TestCase;

class HeicUploadTest extends TestCase
{
    // ---------------------------------------------------------------
    // HEIC Detection (isHeic method)
    // ---------------------------------------------------------------

    public function test_detects_heic_by_extension(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.heic', 'image/jpeg');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_heif_by_extension(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.heif', 'image/jpeg');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_heic_by_mime_type_with_jpg_extension(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        // iOS often sends HEIC data with a .jpg extension
        $file = $this->createUploadedFile('photo.jpg', 'image/heic');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_heif_by_mime_type_with_jpg_extension(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.jpg', 'image/heif');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_heic_sequence_mime_type(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('burst.jpg', 'image/heic-sequence');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_does_not_detect_jpeg_as_heic(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.jpg', 'image/jpeg');
        $this->assertFalse($method->invoke($action, $file));
    }

    public function test_does_not_detect_png_as_heic(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.png', 'image/png');
        $this->assertFalse($method->invoke($action, $file));
    }

    public function test_heic_detection_is_case_insensitive(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFile('photo.HEIC', 'image/jpeg');
        $this->assertTrue($method->invoke($action, $file));
    }

    // ---------------------------------------------------------------
    // Magic Byte Detection
    // ---------------------------------------------------------------

    public function test_detects_heic_by_magic_bytes_with_jpg_extension_and_jpeg_mime(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        // iOS worst case: .jpg extension + image/jpeg MIME but actual HEIC data
        $file = $this->createUploadedFileWithMagicBytes('photo.jpg', 'image/jpeg', 'heic');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_hevc_brand_by_magic_bytes(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFileWithMagicBytes('photo.jpg', 'image/jpeg', 'hevc');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_detects_mif1_brand_by_magic_bytes(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        $file = $this->createUploadedFileWithMagicBytes('photo.jpg', 'image/jpeg', 'mif1');
        $this->assertTrue($method->invoke($action, $file));
    }

    public function test_regular_jpeg_not_detected_by_magic_bytes(): void
    {
        $action = new MakeImageAction();
        $method = new \ReflectionMethod($action, 'isHeic');

        // Real JPEG starts with FF D8, not ftyp
        $file = $this->createUploadedFile('photo.jpg', 'image/jpeg');
        $this->assertFalse($method->invoke($action, $file));
    }

    // ---------------------------------------------------------------
    // End-to-end: genuine HEIC reaches the controller and succeeds
    // ---------------------------------------------------------------

    /**
     * A real HEIC file must pass validation (no longer blocked by the `image` /
     * `dimensions` rules) and upload successfully via the mobile path.
     *
     * MakeImageAction is swapped for a double that returns a real JPEG-backed image,
     * simulating a successful HEIC→JPEG conversion — the actual `convert` shell-out
     * (Bug 1) can't run locally/CI and is verified against production. This test
     * covers Bug 2: that genuine HEIC bytes now get through the validation layer.
     */
    public function test_genuine_heic_uploads_successfully(): void
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $this->swap(
            ReverseGeocodeLocationAction::class,
            (new FakeReverseGeocodingAction())->withAddress([
                'house_number' => '10735',
                'road' => 'Carlisle Pike',
                'city' => 'Latimore Township',
                'county' => 'Adams County',
                'state' => 'Pennsylvania',
                'postcode' => '17324',
                'country' => 'United States of America',
                'country_code' => 'us',
                'suburb' => 'unknown',
            ])
        );

        // Simulate a successful HEIC→JPEG conversion without the `convert` binary.
        $this->swap(MakeImageAction::class, new class extends MakeImageAction {
            public function run(UploadedFile $file, bool $resize = false): array
            {
                return ['image' => Image::make(storage_path('framework/testing/1x1.jpg')), 'exif' => []];
            }
        });

        $user = User::factory()->create(['picked_up' => true]);

        $heic = new UploadedFile(
            storage_path('framework/testing/sample.heic'),
            'photo.heic',
            'image/heic',
            null,
            true
        );

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => $heic,
            'lat' => 40.053,
            'lon' => -77.154,
            'date' => '2026-06-07 12:00:00',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertIsInt($response->json('photo_id'));

        $this->assertDatabaseHas('photos', [
            'id' => $response->json('photo_id'),
            'user_id' => $user->id,
            'platform' => 'mobile',
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a mock UploadedFile that reports the given name and MIME type
     * without needing a real file on disk.
     */
    private function createUploadedFile(string $name, string $mimeType): UploadedFile
    {
        // Create a real temp file so getMimeType() can be overridden
        $tempPath = tempnam(sys_get_temp_dir(), 'heic_test_');
        file_put_contents($tempPath, 'fake image content');

        return new class($tempPath, $name, $mimeType) extends UploadedFile {
            private string $fakeMime;

            public function __construct(string $path, string $name, string $mimeType)
            {
                parent::__construct($path, $name, $mimeType, null, true);
                $this->fakeMime = $mimeType;
            }

            public function getMimeType(): string
            {
                return $this->fakeMime;
            }
        };
    }

    /**
     * Create an UploadedFile with ISO BMFF ftyp header (HEIC magic bytes).
     */
    private function createUploadedFileWithMagicBytes(string $name, string $mimeType, string $brand): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'heic_test_');

        // ISO BMFF: 4 bytes box size + "ftyp" + 4 byte brand + padding
        $header = pack('N', 24) . 'ftyp' . str_pad($brand, 4, "\0") . str_repeat("\0", 8);
        file_put_contents($tempPath, $header);

        return new class($tempPath, $name, $mimeType) extends UploadedFile {
            private string $fakeMime;

            public function __construct(string $path, string $name, string $mimeType)
            {
                parent::__construct($path, $name, $mimeType, null, true);
                $this->fakeMime = $mimeType;
            }

            public function getMimeType(): string
            {
                return $this->fakeMime;
            }
        };
    }
}
