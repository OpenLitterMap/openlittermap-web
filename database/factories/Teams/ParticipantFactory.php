<?php

namespace Database\Factories\Teams;

use App\Models\Teams\Participant;
use App\Models\Teams\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        static $slotCounter = 0;
        $slotCounter++;

        return [
            'team_id' => Team::factory(),
            'slot_number' => $slotCounter,
            'display_name' => "Student {$slotCounter}",
            'session_token' => Participant::generateToken(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
