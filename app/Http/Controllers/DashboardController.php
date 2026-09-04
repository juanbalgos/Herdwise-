<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $farm = $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();
        $animals = $farm->animals()->with('species')->latest()->get();
        $batches = $farm->batches()->with('species')->latest()->get();

        return view('dashboard', compact('farm', 'animals', 'batches'));
    }
}
