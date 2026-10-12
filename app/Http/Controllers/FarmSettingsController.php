<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFarmSettingsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FarmSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        abort_unless($farm->pivot->role === 'owner', 403);

        return view('settings.farm', compact('farm'));
    }

    public function update(UpdateFarmSettingsRequest $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $farm->update($request->validated());

        return redirect()->route('settings.farm.index')->with('success', 'Farm settings updated.');
    }
}
