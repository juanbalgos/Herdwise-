<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        $invitationToken = $request->string('invitation')->toString();
        if ($invitationToken !== '') {
            $invitation = FarmInvitation::where('token_hash', hash('sha256', $invitationToken))->first();
            if ($invitation?->isPending()) {
                $request->session()->put('url.intended', route('invitations.show', ['token' => $invitationToken]));
            }
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended($request->user()->activeFarm() ? route('dashboard') : route('access.waiting'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function showVeterinarianRegistration(Request $request): View
    {
        $invitationToken = $request->string('invitation')->toString();
        $invitation = null;
        if ($invitationToken !== '') {
            $invitation = FarmInvitation::where('token_hash', hash('sha256', $invitationToken))->firstOrFail();
            abort_unless($invitation->isPending(), 410, 'This invitation is no longer active.');
        }

        return view('auth.register-veterinarian', compact('invitation', 'invitationToken'));
    }

    public function registerVeterinarian(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
            'invitation_token' => ['nullable', 'string', 'size:64'],
        ]);
        $invitation = null;
        if (filled($data['invitation_token'] ?? null)) {
            $invitation = FarmInvitation::where('token_hash', hash('sha256', $data['invitation_token']))->firstOrFail();
            abort_unless($invitation->isPending(), 410, 'This invitation is no longer active.');
            abort_unless(Str::lower($data['email']) === Str::lower($invitation->email), 422, 'Use the email address that received the invitation.');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        if ($invitation) {
            return redirect()->route('invitations.show', ['token' => $data['invitation_token']]);
        }

        return redirect()->route('access.waiting');
    }

    public function waitingForAccess(): View
    {
        return view('auth.waiting-for-access');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'], 'farm_name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $farm = Farm::create(['name' => $data['farm_name'], 'slug' => Str::slug($data['farm_name']).'-'.Str::lower(Str::random(5)), 'location' => $data['location'] ?? null]);
            $farm->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to HerdWise. Your farm is ready.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
