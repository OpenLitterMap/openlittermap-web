<?php

namespace Tests\Unit\Models;

use App\Models\AI\Annotation;
use App\Models\Litter\Categories\Ordnance;
use App\Models\Litter\Categories\MilitaryEquipmentRemnant;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    public function test_a_photo_has_proper_casts()
    {
        $casts = Photo::factory()->create()->getCasts();

        $this->assertContains('datetime', $casts);
    }

    public function test_a_photo_has_selected_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertFalse($photo->selected);
    }

    public function test_a_photo_has_picked_up_attribute()
    {
        $photo = Photo::factory()->create();

        $this->assertEquals(!$photo->remaining, $photo->picked_up);
    }

    public function test_a_photo_has_many_boxes()
    {
        $photo = Photo::factory()->create();

        $annotation = Annotation::factory()->create([
            'photo_id' => $photo->id
        ]);

        $this->assertInstanceOf(Collection::class, $photo->boxes);
        $this->assertCount(1, $photo->boxes);
        $this->assertTrue($annotation->is($photo->boxes->first()));
    }

    public function test_photos_have_categories()
    {
        $this->assertNotEmpty(Photo::categories());
        $this->assertEqualsCanonicalizing(
            [
                'ordnance',
                'military_equipment_remnant',
            ],
            Photo::categories()
        );
    }

    public function test_a_photo_has_a_translated_string_of_its_categories()
    {
        $military = MilitaryEquipmentRemnant::factory()->create();
        $ordnance = Ordnance::factory()->create();
        $photo = Photo::factory()->create([
            'military_equipment_remnant_id' => $military->id,
            'ordnance_id' => $ordnance->id
        ]);

        $photo->translate();

        $this->assertEquals(
            $ordnance->translate() . $military->translate(),
            $photo->result_string
        );
    }

    public function test_a_photo_has_a_count_of_total_litter_in_it()
    {
        $military = MilitaryEquipmentRemnant::factory(['weapon' => 1])->create();
        $ordnance = Ordnance::factory(['shell' => 1])->create();
        $photo = Photo::factory()->create([
            'military_equipment_remnant_id' => $military->id,
            'ordnance_id' => $ordnance->id
        ]);

        $photo->total();

        $this->assertEquals($military->total() + $ordnance->total(), $photo->total_litter);
    }

    public function test_a_photo_removes_empty_tags_from_categories()
    {
        $military = MilitaryEquipmentRemnant::factory([
            'weapon' => 1, 'metal_debris' => null
        ])->create();
        $ordnance = Ordnance::factory([
            'shell' => 1, 'land_mine' => null
        ])->create();
        $photo = Photo::factory()->create([
            'military_equipment_remnant_id' => $military->id,
            'ordnance_id' => $ordnance->id
        ]);

        // As a sanity check, we first test that
        // the current state is as we expect it to be
        $this->assertEquals(1, $photo->military_equipment_remnant->weapon);
        $this->assertEquals(1, $photo->ordnance->shell);

        $this->assertArrayHasKey(
            'metal_debris', $photo->military_equipment_remnant->getAttributes()
        );
        $this->assertArrayHasKey(
            'land_mine', $photo->ordnance->getAttributes()
        );

        $photo->tags();

        $this->assertEquals(1, $photo->military_equipment_remnant->weapon);
        $this->assertEquals(1, $photo->ordnance->shell);

        $this->assertArrayNotHasKey(
            'metal_debris', $photo->military_equipment_remnant->getAttributes()
        );
        $this->assertArrayNotHasKey(
            'land_mine', $photo->ordnance->getAttributes()
        );
    }

    public function test_a_photo_has_a_user()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $photo->user);
        $this->assertTrue($user->is($photo->user));
    }

    public function test_a_photo_has_a_military_equipment_remnant_relationship()
    {
        $military = MilitaryEquipmentRemnant::factory()->create();
        $photo = Photo::factory()->create([
            'military_equipment_remnant_id' => $military->id
        ]);

        $this->assertInstanceOf(MilitaryEquipmentRemnant::class, $photo->military_equipment_remnant);
        $this->assertTrue($military->is($photo->military_equipment_remnant));
    }

    public function test_a_photo_has_an_ordnance_relationship()
    {
        $ordnance = Ordnance::factory()->create();
        $photo = Photo::factory()->create(['ordnance_id' => $ordnance->id]);

        $this->assertInstanceOf(Ordnance::class, $photo->ordnance);
        $this->assertTrue($ordnance->is($photo->ordnance));
    }
}
