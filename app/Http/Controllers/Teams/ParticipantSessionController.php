<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Teams\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantSessionController extends Controller
{
    /**
     * Validate a session token and return session info.
     *
     * POST /api/participant/session
     */
    public function enter(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $participant = Participant::with('team:id,name,logo,type_name,participant_sessions_enabled')
            ->where('session_token', $request->input('token'))
            ->where('is_active', true)
            ->first();

        if (! $participant || ! $participant->team?->hasParticipantSessions()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session code.',
            ], 401);
        }

        $participant->update(['last_active_at' => now()]);

        return response()->json([
            'success' => true,
            'session' => [
                'display_name' => $participant->display_name,
                'slot_number' => $participant->slot_number,
                'team_name' => $participant->team->name,
                'team_logo' => $participant->team->logo,
            ],
        ]);
    }
}
