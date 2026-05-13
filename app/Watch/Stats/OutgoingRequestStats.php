<?php

namespace App\Watch\Stats;

use App\Models\OutgoingRequest;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OutgoingRequestStats
{
    private const STATUS_BUCKETS = ['1xx', '2xx', '3xx', '4xx', '5xx', 'failed'];

    /**
     * @return array{
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null,status:array<string,int>},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null,'1xx':int,'2xx':int,'3xx':int,'4xx':int,'5xx':int,failed:int}>
     * }
     */
    public function summary(Project $project, TimeRange $range, ?string $host = null): array
    {
        $base = $this->baseQuery($project, $range, $host);

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(duration_ms) AS total_ms')
            ->selectRaw('MIN(duration_ms) AS min_duration')
            ->selectRaw('MAX(duration_ms) AS max_duration')
            ->selectRaw('AVG(duration_ms) AS avg_duration')
            ->first();

        $durations = (clone $base)
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms')
            ->map(fn ($value) => (float) $value)
            ->all();

        $statusTotals = $this->statusTotals($project, $range, $host);

        return [
            'totals' => [
                'total' => (int) ($stats?->total ?? 0),
                'total_ms' => (float) ($stats?->total_ms ?? 0),
                'min_ms' => $stats?->min_duration !== null ? (float) $stats->min_duration : null,
                'max_ms' => $stats?->max_duration !== null ? (float) $stats->max_duration : null,
                'avg_ms' => $stats?->avg_duration !== null ? (float) $stats->avg_duration : null,
                'p95_ms' => $this->percentile($durations, 0.95),
                'status' => $statusTotals,
            ],
            'buckets' => $this->buckets($project, $range, $host),
        ];
    }

    /**
     * @return LengthAwarePaginator<array-key, array{host:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null,failed:int,'1xx':int,'2xx':int,'3xx':int,'4xx':int,'5xx':int}>
     */
    public function paginatedDomains(
        Project $project,
        TimeRange $range,
        ?string $search,
        ?string $sort,
        ?string $dir,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return StatsPaginator::paginate(
            items: $this->domains($project, $range, $search),
            sortable: [
                'host' => 'string',
                'count' => 'numeric',
                'avg_ms' => 'numeric',
                'p95_ms' => 'numeric',
                'failed' => 'numeric',
                '1xx' => 'numeric',
                '2xx' => 'numeric',
                '3xx' => 'numeric',
                '4xx' => 'numeric',
                '5xx' => 'numeric',
            ],
            sort: $sort ?? 'host',
            dir: $dir ?? 'asc',
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * @return list<array{host:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null,failed:int,'1xx':int,'2xx':int,'3xx':int,'4xx':int,'5xx':int}>
     */
    public function domains(Project $project, TimeRange $range, ?string $search = null): array
    {
        $base = $this->baseQuery($project, $range);

        if ($search !== null && $search !== '') {
            $base->where('host', 'like', '%'.$search.'%');
        }

        $rows = (clone $base)
            ->selectRaw('host')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('AVG(duration_ms) AS avg_ms')
            ->selectRaw('SUM(CASE WHEN status_code IS NULL THEN 1 ELSE 0 END) AS failed_count')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 100 AND 199 THEN 1 ELSE 0 END) AS s1xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS s2xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) AS s3xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS s4xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 500 AND 599 THEN 1 ELSE 0 END) AS s5xx')
            ->groupBy('host')
            ->get();

        $durationsByHost = $this->durationsByHost($project, $range, $search);

        return $rows
            ->map(function ($row) use ($durationsByHost) {
                $host = (string) $row->host;
                $durations = $durationsByHost[$host] ?? [];

                return [
                    'host' => $host,
                    'hash' => sha1($host),
                    'count' => (int) $row->total,
                    'avg_ms' => $row->avg_ms !== null ? (float) $row->avg_ms : null,
                    'p95_ms' => $this->percentile($durations, 0.95),
                    'failed' => (int) $row->failed_count,
                    '1xx' => (int) $row->s1xx,
                    '2xx' => (int) $row->s2xx,
                    '3xx' => (int) $row->s3xx,
                    '4xx' => (int) $row->s4xx,
                    '5xx' => (int) $row->s5xx,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   host:string,
     *   hash:string,
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null,status:array<string,int>},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null,'1xx':int,'2xx':int,'3xx':int,'4xx':int,'5xx':int,failed:int}>,
     *   requests: list<array{id:string,method:string,url:string,status_code:int|null,duration_ms:int|null,source_type:string|null,source_label:string|null,occurred_at:string|null}>
     * }|null
     */
    public function domainDetail(Project $project, TimeRange $range, string $hash): ?array
    {
        $host = OutgoingRequest::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->select('host')
            ->get()
            ->pluck('host')
            ->unique()
            ->first(fn (string $value) => sha1($value) === $hash);

        if ($host === null) {
            return null;
        }

        $summary = $this->summary($project, $range, $host);

        $requests = OutgoingRequest::query()
            ->where('project_id', $project->id)
            ->where('host', $host)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->orderByDesc('occurred_at')
            ->limit(500)
            ->get([
                'id',
                'method',
                'url',
                'status_code',
                'duration_ms',
                'source_type',
                'source_label',
                'occurred_at',
            ])
            ->map(fn (OutgoingRequest $row) => [
                'id' => $row->id,
                'method' => $row->method,
                'url' => $row->url,
                'status_code' => $row->status_code,
                'duration_ms' => $row->duration_ms,
                'source_type' => $row->source_type,
                'source_label' => $row->source_label,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
            ])
            ->all();

        return [
            'host' => $host,
            'hash' => $hash,
            'totals' => $summary['totals'],
            'buckets' => $summary['buckets'],
            'requests' => $requests,
        ];
    }

    /**
     * @return Builder<OutgoingRequest>
     */
    private function baseQuery(Project $project, TimeRange $range, ?string $host = null): Builder
    {
        $query = OutgoingRequest::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to]);

        if ($host !== null) {
            $query->where('host', $host);
        }

        return $query;
    }

    /**
     * @return array<string,int>
     */
    private function statusTotals(Project $project, TimeRange $range, ?string $host = null): array
    {
        $row = $this->baseQuery($project, $range, $host)
            ->selectRaw('SUM(CASE WHEN status_code IS NULL THEN 1 ELSE 0 END) AS failed_count')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 100 AND 199 THEN 1 ELSE 0 END) AS s1xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS s2xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 300 AND 399 THEN 1 ELSE 0 END) AS s3xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) AS s4xx')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 500 AND 599 THEN 1 ELSE 0 END) AS s5xx')
            ->first();

        return [
            '1xx' => (int) ($row?->s1xx ?? 0),
            '2xx' => (int) ($row?->s2xx ?? 0),
            '3xx' => (int) ($row?->s3xx ?? 0),
            '4xx' => (int) ($row?->s4xx ?? 0),
            '5xx' => (int) ($row?->s5xx ?? 0),
            'failed' => (int) ($row?->failed_count ?? 0),
        ];
    }

    /**
     * @return list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null,'1xx':int,'2xx':int,'3xx':int,'4xx':int,'5xx':int,failed:int}>
     */
    private function buckets(Project $project, TimeRange $range, ?string $host = null): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range, $host)
            ->orderBy('occurred_at')
            ->get(['status_code', 'duration_ms', 'occurred_at']);

        $start = $range->from->getTimestamp();
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $buckets[$i] = [
                'bucket' => CarbonImmutable::createFromTimestamp($start + $i * $bucketSeconds)->toIso8601String(),
                'count' => 0,
                '1xx' => 0,
                '2xx' => 0,
                '3xx' => 0,
                '4xx' => 0,
                '5xx' => 0,
                'failed' => 0,
                'durations' => [],
            ];
        }

        foreach ($rows as $row) {
            $offset = $row->occurred_at->getTimestamp() - $start;
            $idx = max(0, min($bucketCount - 1, intdiv($offset, $bucketSeconds)));
            $buckets[$idx]['count']++;
            $bucketKey = $this->statusBucketKey($row->status_code);
            $buckets[$idx][$bucketKey]++;
            if ($row->duration_ms !== null) {
                $buckets[$idx]['durations'][] = (float) $row->duration_ms;
            }
        }

        return array_map(function (array $bucket): array {
            $durations = $bucket['durations'];
            sort($durations);

            return [
                'bucket' => $bucket['bucket'],
                'count' => $bucket['count'],
                'avg_duration' => $durations === [] ? null : array_sum($durations) / count($durations),
                'p95_duration' => $this->percentile($durations, 0.95),
                '1xx' => $bucket['1xx'],
                '2xx' => $bucket['2xx'],
                '3xx' => $bucket['3xx'],
                '4xx' => $bucket['4xx'],
                '5xx' => $bucket['5xx'],
                'failed' => $bucket['failed'],
            ];
        }, $buckets);
    }

    private function statusBucketKey(?int $statusCode): string
    {
        if ($statusCode === null) {
            return 'failed';
        }
        if ($statusCode >= 100 && $statusCode < 200) {
            return '1xx';
        }
        if ($statusCode >= 200 && $statusCode < 300) {
            return '2xx';
        }
        if ($statusCode >= 300 && $statusCode < 400) {
            return '3xx';
        }
        if ($statusCode >= 400 && $statusCode < 500) {
            return '4xx';
        }
        if ($statusCode >= 500 && $statusCode < 600) {
            return '5xx';
        }

        return 'failed';
    }

    /**
     * @return array<string, list<float>>
     */
    private function durationsByHost(Project $project, TimeRange $range, ?string $search): array
    {
        $query = $this->baseQuery($project, $range)->whereNotNull('duration_ms');

        if ($search !== null && $search !== '') {
            $query->where('host', 'like', '%'.$search.'%');
        }

        $buckets = [];
        $query->orderBy('duration_ms')
            ->get(['host', 'duration_ms'])
            ->each(function (OutgoingRequest $row) use (&$buckets) {
                $buckets[$row->host][] = (float) $row->duration_ms;
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
