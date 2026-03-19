<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateParticipantsRequest;
use App\Models\Teams\Participant;
use App\Models\Teams\Team;
use Illuminate\Http\JsonResponse;

class ParticipantController extends Controller
{
    public function index(Team $team): JsonResponse
    {
        $this->authorizeTeamLeader($team);

        $participants = $team->participants()
            ->orderBy('slot_number')
            ->withCount('photos')
            ->get()
            ->map(fn (Participant $p) => [
                'id' => $p->id,
                'slot_number' => $p->slot_number,
                'display_name' => $p->display_name,
                'is_active' => $p->is_active,
                'last_active_at' => $p->last_active_at,
                'photos_count' => $p->photos_count,
            ]);

        return response()->json([
            'success' => true,
            'participants' => $participants,
        ]);
    }

    public function store(CreateParticipantsRequest $request, Team $team): JsonResponse
    {
        $this->authorizeTeamLeader($team);

        if (! $team->hasParticipantSessions()) {
            return response()->json([
                'success' => false,
                'message' => 'Participant sessions not enabled.',
            ], 422);
        }

        $existingMax = $team->participants()->max('slot_number') ?? 0;
        $count = $request->input('count', count($request->input('slots', [])));

        if ($team->max_participants) {
            $total = $team->participants()->count() + $count;
            if ($total > $team->max_participants) {
                return response()->json([
                    'success' => false,
                    'message' => "Exceeds max participants ({$team->max_participants}).",
                ], 422);
            }
        }

        $created = [];
        $slots = $request->input('slots', []);

        for ($i = 0; $i < $count; $i++) {
            $slotNumber = $existingMax + $i + 1;
            $displayName = $slots[$i]['display_name'] ?? "Student {$slotNumber}";

            $participant = Participant::create([
                'team_id' => $team->id,
                'slot_number' => $slotNumber,
                'display_name' => $displayName,
                'session_token' => Participant::generateToken(),
            ]);

            $created[] = [
                'id' => $participant->id,
                'slot_number' => $participant->slot_number,
                'display_name' => $participant->display_name,
                'session_token' => $participant->session_token, // Revealed on create only
            ];
        }

        return response()->json([
            'success' => true,
            'participants' => $created,
        ]);
    }

    public function deactivate(Team $team, Participant $participant): JsonResponse
    {
        $this->authorizeTeamLeader($team);
        $this->ensureBelongsToTeam($team, $participant);

        $participant->deactivate();

        return response()->json(['success' => true, 'message' => 'Participant deactivated.']);
    }

    public function activate(Team $team, Participant $participant): JsonResponse
    {
        $this->authorizeTeamLeader($team);
        $this->ensureBelongsToTeam($team, $participant);

        $participant->activate();

        return response()->json(['success' => true, 'message' => 'Participant activated.']);
    }

    public function resetToken(Team $team, Participant $participant): JsonResponse
    {
        $this->authorizeTeamLeader($team);
        $this->ensureBelongsToTeam($team, $participant);

        $newToken = $participant->resetToken();

        return response()->json([
            'success' => true,
            'session_token' => $newToken,
        ]);
    }

    public function destroy(Team $team, Participant $participant): JsonResponse
    {
        $this->authorizeTeamLeader($team);
        $this->ensureBelongsToTeam($team, $participant);

        $participant->delete();

        return response()->json(['success' => true, 'message' => 'Participant deleted.']);
    }

    private function authorizeTeamLeader(Team $team): void
    {
        $user = auth()->user();

        if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
            abort(403, 'Unauthorized.');
        }
    }

    private function ensureBelongsToTeam(Team $team, Participant $participant): void
    {
        if ((int) $participant->team_id !== (int) $team->id) {
            abort(404, 'Participant not found in this team.');
        }
    }
}
