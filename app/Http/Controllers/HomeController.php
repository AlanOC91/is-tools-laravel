<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Meeting;
use App\Models\ServiceRequest;
use App\Services\FormCatalog;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $stats = [];
        $recent = collect();
        if ($request->user()) {
            $admin = $request->user()->can('admin');
            $query = ServiceRequest::query()->when(! $admin, fn ($q) => $q->where('requested_by', $request->user()->badge_id));
            $stats = ['pending' => (clone $query)->where('state', 'incomplete')->count(), 'completed' => (clone $query)->where('state', 'completed')->count(),
                'meetings' => Meeting::count(), 'delivery_failures' => $admin ? Delivery::where('state', 'failed')->count() : 0];
            $recent = $query->orderByDesc('submitted_date')->limit(5)->get();
        }

        return view('home', ['forms' => FormCatalog::all(), 'stats' => $stats, 'recent' => $recent]);
    }
}
