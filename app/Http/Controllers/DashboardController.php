<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'tenant']);
    }

    /**
     * Show the application dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return view('dashboard', [
            'user' => $user,
            'tenant' => $tenant,
            'stats' => [
                'users' => $tenant->users()->count(),
                'roles' => $tenant->roles()->count(),
                'activities' => $tenant->activities()->count(),
            ],
            'recentActivities' => $tenant->activities()
                ->with('causer')
                ->latest()
                ->take(5)
                ->get(),
            'features' => collect($tenant->settings['features'] ?? [])
                ->filter(fn($enabled) => $enabled === true)
                ->keys(),
            'capabilities' => $tenant->settings['capabilities'] ?? [],
        ]);
    }
}
