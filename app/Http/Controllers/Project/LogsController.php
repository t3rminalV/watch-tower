<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\LogStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogsController extends Controller
{
    public function index(Project $project, Request $request, LogStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '1h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;
        $level = trim($request->string('level')->toString()) ?: null;
        $userName = trim($request->string('user')->toString()) ?: null;
        $page = max(1, (int) $request->integer('page', 1));

        return Inertia::render('projects/logs/index', [
            'logs' => $stats->paginated($project, $range, $search, $level, $userName, $page, 50),
            'users' => $stats->users($project, $range),
            'levels' => LogStats::LEVELS,
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
                'level' => $level,
                'user' => $userName,
            ],
        ]);
    }
}
