<?php

namespace App\Http\Controllers;

use App\Models\FarmInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FarmInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->validInvitation($token);
        if ($request->user()) {
            abort_unless(Str::lower($request->user()->email) === Str::lower($invitation->email), 403, 'Sign in with the invited email address.');
        }
        $invitation->load(['farm', 'inviter']);

        return view('invitations.show', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = DB::transaction(function () use ($request, $token): FarmInvitation {
            $invitation = FarmInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            $this->ensureInvitationCanBeAccepted($request, $invitation);
            $existingMembership = $invitation->farm->users()->whereKey($request->user()->id)->first();

            if ($existingMembership?->pivot?->is_active) {
                abort(422, 'This account already has access to the farm.');
            }

            if ($existingMembership) {
                $invitation->farm->users()->updateExistingPivot($request->user()->id, ['role' => 'veterinarian', 'is_active' => true]);
            } else {
                $invitation->farm->users()->attach($request->user()->id, ['role' => 'veterinarian', 'is_active' => true]);
            }

            $invitation->update(['accepted_at' => now(), 'accepted_by_user_id' => $request->user()->id]);
            $invitation->farm->accessEvents()->create([
                'subject_user_id' => $request->user()->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'veterinarian_access_accepted',
                'metadata' => ['invitation_id' => $invitation->id],
            ]);

            return $invitation;
        });

        $request->session()->put('current_farm_id', $invitation->farm_id);

        return redirect()->route('dashboard')->with('success', 'Veterinarian access accepted. You are now viewing '.$invitation->farm->name.'.');
    }

    private function validInvitation(string $token): FarmInvitation
    {
        $invitation = FarmInvitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless($invitation->isPending(), 410, 'This invitation is no longer active.');

        return $invitation;
    }

    private function ensureInvitationCanBeAccepted(Request $request, FarmInvitation $invitation): void
    {
        abort_unless($invitation->isPending(), 410, 'This invitation is no longer active.');
        abort_unless(Str::lower($request->user()->email) === Str::lower($invitation->email), 403, 'Sign in with the invited email address.');
    }
}
