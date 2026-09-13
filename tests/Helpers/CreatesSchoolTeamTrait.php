<?php

namespace Tests\Helpers;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * - One school team with a teacher (school_manager) and a student, plus the small taxonomy the team editors use.
 * - Call setUpCreatesSchoolTeam() from setUp() after parent::setUp().
 */
trait CreatesSchoolTeamTrait
{
    protected User $teacher;
    protected User $student;
    protected Team $schoolTeam;

    protected function setUpCreatesSchoolTeam(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $schoolType = TeamType::firstOrCreate(['team' => 'school'], ['team' => 'school']);

        $this->teacher = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'manage school team', 'guard_name' => 'web']);
        $role->givePermissionTo('manage school team');
        $this->teacher->assignRole('school_manager');

        $this->schoolTeam = Team::factory()->create([
            'type_id' => $schoolType->id,
            'leader' => $this->teacher->id,
            'safeguarding' => true,
        ]);
        $this->schoolTeam->users()->attach($this->teacher->id);

        $this->student = User::factory()->create();
        $this->schoolTeam->users()->attach($this->student->id);

        $smokingCat = Category::firstOrCreate(['key' => 'smoking']);
        $alcoholCat = Category::firstOrCreate(['key' => 'alcohol']);
        $unclassifiedCat = Category::firstOrCreate(['key' => 'unclassified']);
        $cigaretteButt = LitterObject::firstOrCreate(['key' => 'cigarette_butt']);
        $beerCan = LitterObject::firstOrCreate(['key' => 'beer_can']);
        $otherObj = LitterObject::firstOrCreate(['key' => 'other']);

        CategoryObject::firstOrCreate(['category_id' => $smokingCat->id, 'litter_object_id' => $cigaretteButt->id]);
        CategoryObject::firstOrCreate(['category_id' => $alcoholCat->id, 'litter_object_id' => $beerCan->id]);
        CategoryObject::firstOrCreate(['category_id' => $unclassifiedCat->id, 'litter_object_id' => $otherObj->id]);
    }
}
