<?php

namespace App\Http\Middleware;

use App\Models\Teams\Participant;
use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ParticipantAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Participant-Token')
            ?? $request->input('participant_token');

        if (! $token || strlen($token) !== 64) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing participant token.',
            ], 401);
        }

        $participant = Participant::with('team')
            ->where('session_token', $token)
            ->where('is_active', true)
            ->first();

        if (! $participant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or deactivated session.',
            ], 401);
        }

        $team = $participant->team;

        if (! $team || ! $team->hasParticipantSessions()) {
            return response()->json([
                'success' => false,
                'message' => 'Participant sessions not enabled for this team.',
            ], 403);
        }

        $facilitator = User::find($team->leader);

        if (! $facilitator) {
            return response()->json([
                'success' => false,
                'message' => 'Team facilitator not found.',
            ], 500);
        }

        // Authenticate as the facilitator (stateless, per-request only)
        Auth::setUser($facilitator);

        // Touch last_active_at (throttled to once per minute)
        $cacheKey = "participant:{$participant->id}:active";
        if (! Cache::has($cacheKey)) {
            $participant->update(['last_active_at' => now()]);
            Cache::put($cacheKey, true, 60);
        }

        // Attach participant and team to request for downstream controllers
        $request->attributes->set('participant', $participant);
        $request->attributes->set('participant_team', $team);

        return $next($request);
    }
}
