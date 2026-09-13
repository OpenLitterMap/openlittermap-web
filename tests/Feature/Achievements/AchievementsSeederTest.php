<?php
namespace Tests\Feature\Achievements;

use Tests\TestCase;
use Database\Seeders\AchievementsSeeder;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

class AchievementsSeederTest extends TestCase
{
    public function test_repeat_seeding_only_adds_missing_definitions_including_null_tag_ids(): void
    {
        config(['achievements.milestones' => [1, 10]]);
        $old = now()->subYear();
        $id = DB::table('achievements')->insertGetId(['type' => 'uploads', 'tag_id' => null, 'threshold' => 1,
            'metadata' => '{"keep":true}', 'created_at' => $old, 'updated_at' => $old]);
        DB::table('user_achievements')->insert(['user_id' => User::factory()->create()->id, 'achievement_id' => $id, 'created_at' => $old]);
        $definition = DB::table('achievements')->where('id', $id)->first();
        $earned = DB::table('user_achievements')->get()->toJson();
        $this->seed(AchievementsSeeder::class);
        $before = DB::table('achievements')->orderBy('id')->get()->toJson();
        $this->travel(1)->days();
        $this->seed(AchievementsSeeder::class);
        $this->assertSame($before, DB::table('achievements')->orderBy('id')->get()->toJson());
        $this->assertEquals($definition, DB::table('achievements')->where('id', $id)->first());
        $object = LitterObject::create(['key' => 'new_achievement_object']);
        $this->seed(AchievementsSeeder::class);
        $this->assertSame(2, DB::table('achievements')->where('type', 'object')->where('tag_id', $object->id)->count());
        $this->assertSame($earned, DB::table('user_achievements')->get()->toJson());
    }
}
