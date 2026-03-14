<?php

namespace App\Console\Commands\Teams;

use App\Models\Teams\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateTeamMembers extends Command
{
    protected $signature = 'teams:recalculate-members
        {--team= : Recalculate a specific team ID}
        {--dry-run : Show discrepancies without updating}';

    protected $description = 'Recalculate teams.members from actual team_user pivot count';

    public function handle(): int
    {
        $teamId = $this->option('team');
        $dryRun = $this->option('dry-run');

        $query = Team::query();

        if ($teamId) {
            $query->where('id', $teamId);
        }

        $teams = $query->get(['id', 'name', 'members']);
        $fixed = 0;

        foreach ($teams as $team) {
            $actual = DB::table('team_user')->where('team_id', $team->id)->count();

            if ($actual !== $team->members) {
                $this->warn("Team {$team->id} ({$team->name}): stored={$team->members}, actual={$actual}");

                if (! $dryRun) {
                    $team->members = $actual;
                    $team->save();
                }

                $fixed++;
            }
        }

        if ($fixed === 0) {
            $this->info('All team member counts are correct.');
        } elseif ($dryRun) {
            $this->info("{$fixed} team(s) have incorrect member counts (dry run — no changes made).");
        } else {
            $this->info("{$fixed} team(s) updated.");
        }

        return 0;
    }
}
