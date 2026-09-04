<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFarmInvitationRequest;
use App\Models\FarmInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FarmTeamController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        abort_unless($farm->pivot->role === 'owner', 403);

        $members = $farm->users()->orderBy('name')->get();
        $pendingInvitations = $farm->invitations()
            ->with('inviter')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->latest()
            ->get();
        $accessEvents = $farm->accessEvents()
            ->with(['subjectUser', 'actorUser'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('team.index', compact('farm', 'members', 'pendingInvitations', 'accessEvents'));
    }

    public function storeInvitation(StoreFarmInvitationRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $data = $request->validated();
        $veterinarian = User::where('email', $data['email'])->first();
        $membership = $veterinarian ? $farm->users()->whereKey($veterinarian->id)->first() : null;

        if ($membership?->pivot?->is_active) {
            throw ValidationException::withMessages(['email' => 'This person already has active access to the farm.']);
        }

        $token = Str::random(64);
        DB::transaction(function () use ($farm, $request, $data, $token, $veterinarian): void {
            $farm->invitations()
                ->where('email', $data['email'])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = $farm->invitations()->create([
                'email' => $data['email'],
                'role' => 'veterinarian',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays((int) $data['expires_in_days']),
                'invited_by_user_id' => $request->user()->id,
            ]);

            $farm->accessEvents()->create([
                'subject_user_id' => $veterinarian?->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'veterinarian_invited',
                'metadata' => ['email' => $data['email'], 'invitation_id' => $invitation->id],
            ]);

        });

        return redirect()->route('team.index')
            ->with('success', 'Veterinarian invitation created. Share the private link below.')
            ->with('invitation_link', route('invitations.show', ['token' => $token]));
    }

    public function revokeMember(Request $request, User $member): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_unless($farm->pivot->role === 'owner', 403);
        $membership = $farm->users()->whereKey($member->id)->firstOrFail();
        abort_if($membership->pivot->role === 'owner' || $member->is($request->user()), 422, 'The farm owner cannot be removed.');
        abort_unless($membership->pivot->is_active, 422, 'This member is already inactive.');
        $role = $membership->pivot->role;

        DB::transaction(function () use ($farm, $request, $member, $role): void {
            $farm->users()->updateExistingPivot($member->id, ['is_active' => false]);
            $farm->accessEvents()->create([
                'subject_user_id' => $member->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'farm_access_revoked',
                'metadata' => ['role' => $role],
            ]);
        });

        return back()->with('success', $member->name.' can no longer access this farm.');
    }

    public function revokeInvitation(Request $request, FarmInvitation $invitation): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        abort_unless($farm->pivot->role === 'owner' && $invitation->farm_id === $farm->id, 404);
        abort_unless($invitation->isPending(), 422);

        DB::transaction(function () use ($farm, $request, $invitation): void {
            $invitation->update(['revoked_at' => now()]);
            $farm->accessEvents()->create([
                'actor_user_id' => $request->user()->id,
                'event_type' => 'invitation_revoked',
                'metadata' => ['email' => $invitation->email, 'invitation_id' => $invitation->id],
            ]);
        });

        return back()->with('success', 'Invitation revoked.');
    }
}
