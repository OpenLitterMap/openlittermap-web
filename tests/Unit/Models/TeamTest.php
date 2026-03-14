<?php

namespace Tests\Unit\Models;

use App\Models\Teams\Team;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamTest extends TestCase
{
    public function test_teams_database_has_expected_columns()
    {
        $this->assertEqualsCanonicalizing(
            [
                'id',
                'name',
                'members',
                'leader',
                'created_at',
                'updated_at',
                'type_id',
                'type_name',
                'created_by',
                'identifier',
                'leaderboards',
                'is_trusted',
                'safeguarding',
                'contact_email',
                'academic_year',
                'class_group',
                'county',
                'logo',
                'max_participants',
                'participant_sessions_enabled',
            ],
            Schema::getColumnListing('teams')
        );
    }

    public function test_a_team_belongs_to_many_users()
    {
        $team = Team::factory()->create();
        $users = User::factory(3)->create();
        foreach ($users as $user) {
            $user->teams()->attach($team);
        }

        $team->refresh();
        $this->assertInstanceOf(Collection::class, $team->users);
        $this->assertCount(3, $team->users);
        $this->assertTrue($users->first()->is($team->users->first()));
    }
}
