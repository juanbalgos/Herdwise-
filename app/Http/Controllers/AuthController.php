<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister()
    {
        return view('auth.register');
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
