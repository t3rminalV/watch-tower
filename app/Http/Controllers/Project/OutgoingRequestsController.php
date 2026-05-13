<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\OutgoingRequestStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutgoingRequestsController extends Controller
{
    public function index(Project $project, Request $request, OutgoingRequestStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '24h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;
        $sort = $request->string('sort')->toString() ?: null;
        $dir = $request->string('dir')->toString() ?: null;
        $page = max(1, (int) $request->integer('page', 1));

        return Inertia::render('projects/outgoing-requests/index', [
            'summary' => $stats->summary($project, $range),
            'domains' => $stats->paginatedDomains($project, $range, $search, $sort, $dir, $page, 25),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
                'sort' => $sort ?? 'host',
                'dir' => $dir ?? 'asc',
            ],
        ]);
    }

    public function show(Project $project, Request $request, OutgoingRequestStats $stats, string $domain): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '7d')->toString());

        $detail = $stats->domainDetail($project, $range, $domain);

        abort_if($detail === null, 404);

        return Inertia::render('projects/outgoing-requests/show', [
            'detail' => $detail,
            'selectedRange' => $range->label,
        ]);
    }
}
