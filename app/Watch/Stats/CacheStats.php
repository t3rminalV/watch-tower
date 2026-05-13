<?php

namespace App\Watch\Stats;

use App\Models\CacheEvent;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CacheStats
{
    private const OPERATIONS = ['hit', 'miss', 'write', 'delete'];

    private const FAILURE_OPERATIONS = ['write', 'delete'];

    /**
     * @return array{
     *   totals: array{hit:int, miss:int, write:int, delete:int, total:int},
     *   buckets: list<array{bucket:string, hit:int, miss:int, write:int, delete:int}>
     * }
     */
    public function summary(Project $project, TimeRange $range): array
    {
        $counts = $this->baseQuery($project, $range)
            ->selectRaw('operation, COUNT(*) AS total')
            ->groupBy('operation')
            ->pluck('total', 'operation');

        $totals = [
            'hit' => (int) ($counts['hit'] ?? 0),
            'miss' => (int) ($counts['miss'] ?? 0),
            'write' => (int) ($counts['write'] ?? 0),
            'delete' => (int) ($counts['delete'] ?? 0),
            'total' => 0,
        ];
        $totals['total'] = $totals['hit'] + $totals['miss'] + $totals['write'] + $totals['delete'];

        return [
            'totals' => $totals,
            'buckets' => $this->buckets($project, $range),
        ];
    }

    /**
     * @return array{
     *   totals: array{write:int, delete:int, total:int},
     *   buckets: list<array{bucket:string, write:int, delete:int}>
     * }
     */
    public function failures(Project $project, TimeRange $range): array
    {
        $counts = $this->baseQuery($project, $range)
            ->where('succeeded', false)
            ->whereIn('operation', self::FAILURE_OPERATIONS)
            ->selectRaw('operation, COUNT(*) AS total')
            ->groupBy('operation')
            ->pluck('total', 'operation');

        $totals = [
            'write' => (int) ($counts['write'] ?? 0),
            'delete' => (int) ($counts['delete'] ?? 0),
            'total' => 0,
        ];
        $totals['total'] = $totals['write'] + $totals['delete'];

        return [
            'totals' => $totals,
            'buckets' => $this->failureBuckets($project, $range),
        ];
    }

    /**
     * @return list<array{key:string, hash:string, hit_pct:float|null, hit:int, miss:int, write:int, delete:int, failures:int, total:int}>
     */
    public function topKeys(Project $project, TimeRange $range, ?string $search, int $limit = 100): array
    {
        $query = $this->baseQuery($project, $range)
            ->selectRaw('`key`')
            ->selectRaw("SUM(CASE WHEN operation = 'hit' THEN 1 ELSE 0 END) AS hit_count")
            ->selectRaw("SUM(CASE WHEN operation = 'miss' THEN 1 ELSE 0 END) AS miss_count")
            ->selectRaw("SUM(CASE WHEN operation = 'write' THEN 1 ELSE 0 END) AS write_count")
            ->selectRaw("SUM(CASE WHEN operation = 'delete' THEN 1 ELSE 0 END) AS delete_count")
            ->selectRaw("SUM(CASE WHEN succeeded = 0 AND operation IN ('write','delete') THEN 1 ELSE 0 END) AS failure_count")
            ->groupBy('key');

        if ($search !== null && $search !== '') {
            $query->where('key', 'like', '%'.$search.'%');
        }

        $rows = $query
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get();

        return $rows
            ->map(function ($row) {
                $hits = (int) $row->hit_count;
                $misses = (int) $row->miss_count;
                $writes = (int) $row->write_count;
                $deletes = (int) $row->delete_count;
                $failures = (int) $row->failure_count;
                $reads = $hits + $misses;
                $key = (string) $row->key;

                return [
                    'key' => $key,
                    'hash' => sha1($key),
                    'hit_pct' => $reads > 0 ? round(($hits / $reads) * 100, 1) : null,
                    'hit' => $hits,
                    'miss' => $misses,
                    'write' => $writes,
                    'delete' => $deletes,
                    'failures' => $failures,
                    'total' => $hits + $misses + $writes + $deletes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Builder<CacheEvent>
     */
    private function baseQuery(Project $project, TimeRange $range): Builder
    {
        return CacheEvent::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to]);
    }

    /**
     * @return list<array{bucket:string, hit:int, miss:int, write:int, delete:int}>
     */
    private function buckets(Project $project, TimeRange $range): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range)
            ->orderBy('occurred_at')
            ->get(['operation', 'occurred_at']);

        $start = $range->from->getTimestamp();
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $buckets[$i] = [
                'bucket' => CarbonImmutable::createFromTimestamp($start + $i * $bucketSeconds)->toIso8601String(),
                'hit' => 0,
                'miss' => 0,
                'write' => 0,
                'delete' => 0,
            ];
        }

        foreach ($rows as $row) {
            $offset = $row->occurred_at->getTimestamp() - $start;
            $idx = max(0, min($bucketCount - 1, intdiv($offset, $bucketSeconds)));
            if (in_array($row->operation, self::OPERATIONS, true)) {
                $buckets[$idx][$row->operation]++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @return list<array{bucket:string, write:int, delete:int}>
     */
    private function failureBuckets(Project $project, TimeRange $range): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range)
            ->where('succeeded', false)
            ->whereIn('operation', self::FAILURE_OPERATIONS)
            ->orderBy('occurred_at')
            ->get(['operation', 'occurred_at']);

        $start = $range->from->getTimestamp();
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $buckets[$i] = [
                'bucket' => CarbonImmutable::createFromTimestamp($start + $i * $bucketSeconds)->toIso8601String(),
                'write' => 0,
                'delete' => 0,
            ];
        }

        foreach ($rows as $row) {
            $offset = $row->occurred_at->getTimestamp() - $start;
            $idx = max(0, min($bucketCount - 1, intdiv($offset, $bucketSeconds)));
            $buckets[$idx][$row->operation]++;
        }

        return array_values($buckets);
    }
}
