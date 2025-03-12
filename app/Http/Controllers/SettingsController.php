<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Settings;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request){
        $credential = Settings::first();
        return Inertia::render('Dashboard',[
            'credential' => $credential,
        ]);
    }
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'scope' => 'nullable|string',
            'grant_type' => 'nullable|string',
            'urls' => 'nullable|array',
            'additional_info' => 'nullable|array',
        ]);

        $credential = Settings::updateOrCreate(
            ['id' => 1],
            $validatedData
        );
        return redirect()->back()->with('success', 'Settings updated successfully!');
    }
}
