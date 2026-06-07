<?php

namespace App\Watch\Stats;

use App\Models\Project;
use App\Models\TraceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class QueryStats
{
    /**
     * @return LengthAwarePaginator<array-key, array{hash:string,sql:string,connection:string|null,total:int,total_ms:float,avg_ms:float|null,p95_ms:float|null}>
     */
    public function paginatedQueries(
        Project $project,
        TimeRange $range,
        ?string $search,
        ?string $sort,
        ?string $dir,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return StatsPaginator::paginate(
            items: $this->queries($project, $range, $search),
            sortable: [
                'sql' => 'string',
                'connection' => 'string',
                'total' => 'numeric',
                'total_ms' => 'numeric',
                'avg_ms' => 'numeric',
                'p95_ms' => 'numeric',
            ],
            sort: $sort ?? 'total_ms',
            dir: $dir ?? 'desc',
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * @return array{
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     * }
     */
    public function summary(Project $project, TimeRange $range, ?string $sql = null): array
    {
        $base = $this->baseQuery($project, $range, $sql);

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(duration_ms) AS total_ms')
            ->selectRaw('MIN(duration_ms) AS min_duration')
            ->selectRaw('MAX(duration_ms) AS max_duration')
            ->selectRaw('AVG(duration_ms) AS avg_duration')
            ->first();

        $durations = (clone $base)
            ->pluck('duration_ms')
            ->map(fn ($value) => (float) $value)
            ->all();

        return [
            'totals' => [
                'total' => (int) ($stats?->total ?? 0),
                'total_ms' => (float) ($stats?->total_ms ?? 0),
                'min_ms' => $stats?->min_duration !== null ? (float) $stats->min_duration : null,
                'max_ms' => $stats?->max_duration !== null ? (float) $stats->max_duration : null,
                'avg_ms' => $stats?->avg_duration !== null ? (float) $stats->avg_duration : null,
                'p95_ms' => $this->percentile($durations, 0.95),
            ],
            'buckets' => $this->buckets($project, $range, $sql),
        ];
    }

    /**
     * @return list<array{hash:string,sql:string,connection:string|null,total:int,total_ms:float,avg_ms:float|null,p95_ms:float|null}>
     */
    public function queries(Project $project, TimeRange $range, ?string $search = null): array
    {
        $base = $this->baseQuery($project, $range);

        if ($search !== null && $search !== '') {
            $base->where('sql', 'like', '%'.$search.'%');
        }

        $rows = (clone $base)
            ->select('sql')
            ->selectRaw('MAX(connection_name) AS connection_name')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(duration_ms) AS total_ms')
            ->selectRaw('AVG(duration_ms) AS avg_ms')
            ->groupBy('sql')
            ->get();

        $durationsBySql = $this->durationsBySql($project, $range, $search);

        return $rows
            ->map(function ($row) use ($durationsBySql) {
                $sql = (string) $row->sql;
                $durations = $durationsBySql[$sql] ?? [];

                return [
                    'hash' => sha1($sql),
                    'sql' => $sql,
                    'connection' => $row->connection_name !== null ? (string) $row->connection_name : null,
                    'total' => (int) $row->total,
                    'total_ms' => (float) $row->total_ms,
                    'avg_ms' => $row->avg_ms !== null ? (float) $row->avg_ms : null,
                    'p95_ms' => $this->percentile($durations, 0.95),
                ];
            })
            ->sortByDesc('total_ms')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   sql:string,
     *   hash:string,
     *   connection:string|null,
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>,
     *   calls: list<array{id:string,duration_ms:float,row_count:int|null,is_slow:bool,is_n_plus_one:bool,occurred_at:string|null}>
     * }|null
     */
    public function queryDetail(Project $project, TimeRange $range, string $hash): ?array
    {
        $sql = TraceQuery::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->select('sql')
            ->get()
            ->pluck('sql')
            ->unique()
            ->first(fn (string $value) => sha1($value) === $hash);

        if ($sql === null) {
            return null;
        }

        $summary = $this->summary($project, $range, $sql);

        $connection = TraceQuery::query()
            ->where('project_id', $project->id)
            ->where('sql', $sql)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->value('connection_name');

        $calls = TraceQuery::query()
            ->where('project_id', $project->id)
            ->where('sql', $sql)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get(['id', 'duration_ms', 'row_count', 'is_slow', 'is_n_plus_one', 'occurred_at'])
            ->map(fn (TraceQuery $row) => [
                'id' => $row->id,
                'duration_ms' => (float) $row->duration_ms,
                'row_count' => $row->row_count,
                'is_slow' => (bool) $row->is_slow,
                'is_n_plus_one' => (bool) $row->is_n_plus_one,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
            ])
            ->all();

        return [
            'sql' => $sql,
            'hash' => $hash,
            'connection' => $connection !== null ? (string) $connection : null,
            'totals' => $summary['totals'],
            'buckets' => $summary['buckets'],
            'calls' => $calls,
        ];
    }

    /**
     * @return Builder<TraceQuery>
     */
    private function baseQuery(Project $project, TimeRange $range, ?string $sql = null): Builder
    {
        $query = TraceQuery::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to]);

        if ($sql !== null) {
            $query->where('sql', $sql);
        }

        return $query;
    }

    /**
     * @return list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     */
    private function buckets(Project $project, TimeRange $range, ?string $sql = null): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range, $sql)
            ->orderBy('occurred_at')
            ->get(['duration_ms', 'occurred_at']);

        $start = $range->from->getTimestamp();
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $buckets[$i] = [
                'bucket' => CarbonImmutable::createFromTimestamp($start + $i * $bucketSeconds)->toIso8601String(),
                'count' => 0,
                'durations' => [],
            ];
        }

        foreach ($rows as $row) {
            $offset = $row->occurred_at->getTimestamp() - $start;
            $idx = max(0, min($bucketCount - 1, intdiv($offset, $bucketSeconds)));
            $buckets[$idx]['count']++;
            $buckets[$idx]['durations'][] = (float) $row->duration_ms;
        }

        return array_map(function (array $bucket): array {
            $durations = $bucket['durations'];
            sort($durations);

            return [
                'bucket' => $bucket['bucket'],
                'count' => $bucket['count'],
                'avg_duration' => $durations === [] ? null : array_sum($durations) / count($durations),
                'p95_duration' => $this->percentile($durations, 0.95),
            ];
        }, $buckets);
    }

    /**
     * @return array<string, list<float>>
     */
    private function durationsBySql(Project $project, TimeRange $range, ?string $search): array
    {
        $query = $this->baseQuery($project, $range);

        if ($search !== null && $search !== '') {
            $query->where('sql', 'like', '%'.$search.'%');
        }

        $buckets = [];
        $query->orderBy('duration_ms')
            ->get(['sql', 'duration_ms'])
            ->each(function (TraceQuery $row) use (&$buckets) {
                $buckets[$row->sql][] = (float) $row->duration_ms;
            });

        return $buckets;
    }

    /**
     * @param  list<int|float>  $values
     */
    private function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }
        $sorted = $values;
        sort($sorted);

        $index = (int) floor($p * (count($sorted) - 1));

        return (float) $sorted[$index];
    }
}
