<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $animals = $farm->animals()->notArchived()->with('species')->latest()->get();
        $batches = $farm->batches()->notArchived()->with('species')->latest()->get();
        $reminders = $farm->reminders()->with(['animal', 'batch'])->where('status', 'pending')->orderBy('due_on')->limit(6)->get();

        return view('dashboard', compact('farm', 'animals', 'batches', 'reminders'));
    }
}
