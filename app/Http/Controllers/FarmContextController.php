<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FarmContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['farm_id' => ['required', 'integer']]);
        $farm = $this->activateFarmContext($request, (int) $data['farm_id']);

        return redirect()->route('dashboard')->with('success', 'Now viewing '.$farm->name.'.');
    }
}
