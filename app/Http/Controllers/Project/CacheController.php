<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\CacheStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CacheController extends Controller
{
    public function index(Project $project, Request $request, CacheStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '24h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;

        return Inertia::render('projects/cache/index', [
            'summary' => $stats->summary($project, $range),
            'failures' => $stats->failures($project, $range),
            'keys' => $stats->topKeys($project, $range, $search, 100),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
