<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function currentFarm(Request $request): Farm
    {
        $preferredFarmId = (int) $request->session()->get('current_farm_id', 0);
        $farm = $request->user()->activeFarm($preferredFarmId);
        abort_unless($farm, 404);

        return $farm;
    }

    protected function currentFarmRole(Request $request): string
    {
        return $this->currentFarm($request)->pivot->role;
    }

    protected function activateFarmContext(Request $request, int $farmId): Farm
    {
        $farm = $request->user()->farms()
            ->wherePivot('is_active', true)
            ->whereKey($farmId)
            ->firstOrFail();

        $request->session()->put('current_farm_id', $farm->id);

        return $farm;
    }
}
