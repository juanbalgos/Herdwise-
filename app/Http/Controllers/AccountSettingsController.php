<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateAccountProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('settings.account', ['user' => $request->user()]);
    }

    public function updateProfile(UpdateAccountProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('settings.account.index')->with('success', 'Account details updated.');
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['password']));

        return redirect()->route('settings.account.index')->with('success', 'Password updated.');
    }
}
