<?php

namespace App\Console\Commands;

use App\Models\Users\User;
use Illuminate\Console\Command;

class AssignSchoolManager extends Command
{
    protected $signature = 'school:assign-manager {email : The email or username of the user}';

    protected $description = 'Assign the school_manager role to a user, allowing them to create and manage school teams';

    public function handle(): int
    {
        $identifier = $this->argument('email');

        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user) {
            $this->error("User not found: {$identifier}");
            return self::FAILURE;
        }

        if ($user->hasRole('school_manager')) {
            $this->warn("{$user->name} ({$user->email}) is already a school manager.");
            return self::SUCCESS;
        }

        $user->assignRole('school_manager');

        // Grant 1 team creation slot if they have none
        if ($user->remaining_teams < 1) {
            $user->remaining_teams = 1;
            $user->save();
        }

        $this->info("{$user->name} ({$user->email}) is now a school manager.");
        $this->line("  They can now create 1 team and manage safeguarding settings.");

        return self::SUCCESS;
    }
}
