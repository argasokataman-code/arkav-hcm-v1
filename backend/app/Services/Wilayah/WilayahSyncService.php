<?php

namespace App\Services\Wilayah;

use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WilayahSyncService
{
    public const PROGRESS_CACHE_KEY = 'wilayah:sync:progress';

    private const PROGRESS_CACHE_TTL_SECONDS = 86400;

    private const BASE_URL = 'https://wilayah.id/api';

    private const POOL_CHUNK_SIZE = 20;

    private const UPSERT_CHUNK_SIZE = 500;

    public function sync(): array
    {
        $syncedAt = Carbon::now();

        $summary = [
            'provinces' => 0,
            'regencies' => 0,
            'districts' => 0,
            'villages' => 0,
            'syncedAt' => $syncedAt->toDateTimeString(),
            'source' => 'wilayah.id',
        ];

        $this->putProgress([
            'running' => true,
            'progress' => 0,
            'stage' => 'preparing',
            'message' => 'Memulai sinkronisasi data wilayah.',
            'processed' => 0,
            'total' => 0,
            'error' => null,
            'summary' => null,
            'startedAt' => Carbon::now()->toIso8601String(),
            'updatedAt' => Carbon::now()->toIso8601String(),
            'finishedAt' => null,
        ]);

        try {
            $this->putProgress([
                'progress' => 5,
                'stage' => 'provinces',
                'message' => 'Mengambil data provinces dari API wilayah.id.',
                'processed' => 0,
                'total' => 0,
            ]);

            $provinceResponse = $this->fetchRootResponse('provinces.json');
            if (! $provinceResponse || ! $provinceResponse->ok()) {
                throw new \RuntimeException('Unable to fetch province list from wilayah.id.');
            }

            $provinceRows = $this->extractRows($provinceResponse);
            $provinceCodes = array_values(array_filter(array_map(
                fn (array $row): string => $this->normalizeText((string) ($row['code'] ?? '')),
                $provinceRows
            )));
            $summary['provinces'] = $this->upsertRows(WilayahProvince::query(), $provinceRows, ['code'], ['name', 'updated_at'], $syncedAt);
            if ($provinceCodes !== []) {
                WilayahProvince::query()->whereNotIn('code', $provinceCodes)->delete();
            }

            $this->putProgress([
                'progress' => 20,
                'stage' => 'provinces',
                'message' => 'Provinces tersinkronisasi.',
                'processed' => count($provinceRows),
                'total' => count($provinceRows),
                'summary' => $summary,
            ]);

            $provinces = WilayahProvince::query()->orderBy('code')->get(['id', 'code']);
            $this->syncChildRows(
                $provinces,
                fn (object $province): string => 'regencies/'.$province->code.'.json',
                fn (object $province, array $item) => [
                    'province_id' => (int) $province->id,
                    'code' => $this->normalizeText((string) ($item['code'] ?? '')),
                    'name' => $this->normalizeText((string) ($item['name'] ?? '')),
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ],
                WilayahRegency::query(),
                ['code'],
                ['province_id', 'name', 'updated_at'],
                $syncedAt,
                $summary,
                'regencies',
                'province_id',
                function (int $processed, int $total) use (&$summary): void {
                    $percent = 20 + (int) floor(($total > 0 ? ($processed / $total) : 1) * 25);
                    $this->putProgress([
                        'progress' => min(45, $percent),
                        'stage' => 'regencies',
                        'message' => 'Sync regencies dari seluruh provinces.',
                        'processed' => $processed,
                        'total' => $total,
                        'summary' => $summary,
                    ]);
                }
            );

            $regencies = WilayahRegency::query()->orderBy('code')->get(['id', 'code']);
            $this->syncChildRows(
                $regencies,
                fn (object $regency): string => 'districts/'.$regency->code.'.json',
                fn (object $regency, array $item) => [
                    'regency_id' => (int) $regency->id,
                    'code' => $this->normalizeText((string) ($item['code'] ?? '')),
                    'name' => $this->normalizeText((string) ($item['name'] ?? '')),
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ],
                WilayahDistrict::query(),
                ['code'],
                ['regency_id', 'name', 'updated_at'],
                $syncedAt,
                $summary,
                'districts',
                'regency_id',
                function (int $processed, int $total) use (&$summary): void {
                    $percent = 45 + (int) floor(($total > 0 ? ($processed / $total) : 1) * 25);
                    $this->putProgress([
                        'progress' => min(70, $percent),
                        'stage' => 'districts',
                        'message' => 'Sync districts dari seluruh regencies.',
                        'processed' => $processed,
                        'total' => $total,
                        'summary' => $summary,
                    ]);
                }
            );

            $districts = WilayahDistrict::query()->orderBy('code')->get(['id', 'code']);
            $this->syncChildRows(
                $districts,
                fn (object $district): string => 'villages/'.$district->code.'.json',
                fn (object $district, array $item) => [
                    'district_id' => (int) $district->id,
                    'code' => $this->normalizeText((string) ($item['code'] ?? '')),
                    'name' => $this->normalizeText((string) ($item['name'] ?? '')),
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ],
                WilayahVillage::query(),
                ['code'],
                ['district_id', 'name', 'updated_at'],
                $syncedAt,
                $summary,
                'villages',
                'district_id',
                function (int $processed, int $total) use (&$summary): void {
                    $percent = 70 + (int) floor(($total > 0 ? ($processed / $total) : 1) * 25);
                    $this->putProgress([
                        'progress' => min(95, $percent),
                        'stage' => 'villages',
                        'message' => 'Sync villages dari seluruh districts.',
                        'processed' => $processed,
                        'total' => $total,
                        'summary' => $summary,
                    ]);
                }
            );

            $this->flushTotalCountCache();

            $this->putProgress([
                'running' => false,
                'progress' => 100,
                'stage' => 'completed',
                'message' => 'Sinkronisasi data wilayah selesai.',
                'error' => null,
                'summary' => $summary,
                'finishedAt' => Carbon::now()->toIso8601String(),
            ]);

            return $summary;
        } catch (Throwable $throwable) {
            $this->putProgress([
                'running' => false,
                'stage' => 'failed',
                'message' => 'Sinkronisasi data wilayah gagal.',
                'error' => $throwable->getMessage(),
                'finishedAt' => Carbon::now()->toIso8601String(),
            ]);

            throw $throwable;
        }
    }

    /**
     * @return Response|null
     */
    private function fetchRootResponse(string $path): ?Response
    {
        try {
            return Http::acceptJson()->timeout(30)->get($this->endpoint($path));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  Collection<int, object>  $parents
     */
    private function syncChildRows(
        Collection $parents,
        callable $pathResolver,
        callable $rowMapper,
        $query,
        array $uniqueBy,
        array $updateColumns,
        Carbon $syncedAt,
        array &$summary,
        string $summaryKey,
        string $parentColumn,
        ?callable $progressReporter = null
    ): void {
        $processedParents = 0;
        $totalParents = $parents->count();

        foreach ($parents->chunk(self::POOL_CHUNK_SIZE) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $pathResolver): array {
                $requests = [];

                foreach ($chunk as $parent) {
                    $requests[$parent->code] = $pool->as((string) $parent->code)
                        ->acceptJson()
                        ->timeout(30)
                        ->get($this->endpoint($pathResolver($parent)));
                }

                return $requests;
            });

            $rows = [];
            foreach ($chunk as $parent) {
                $response = $responses[$parent->code] ?? null;
                if (! $response || ! $response->ok()) {
                    continue;
                }

                $payload = $this->extractRows($response);
                $codes = [];
                foreach ($payload as $item) {
                    $row = $rowMapper($parent, $item);
                    if (($row['code'] ?? '') === '' || ($row['name'] ?? '') === '') {
                        continue;
                    }
                    $codes[] = $row['code'];
                    $rows[] = $row;
                }

                if ($codes === []) {
                    $query->where($parentColumn, (int) $parent->id)->delete();
                    continue;
                }

                $query->where($parentColumn, (int) $parent->id)
                    ->whereNotIn('code', array_values(array_unique($codes)))
                    ->delete();
            }

            $summary[$summaryKey] += $this->upsertRows($query, $rows, $uniqueBy, $updateColumns, $syncedAt);

            $processedParents += $chunk->count();
            if ($progressReporter !== null) {
                $progressReporter($processedParents, $totalParents);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertRows($query, array $rows, array $uniqueBy, array $updateColumns, Carbon $syncedAt): int
    {
        $count = 0;

        foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            $query->upsert($chunk, $uniqueBy, $updateColumns);
            $count += count($chunk);
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(?Response $response): array
    {
        if (! $response || ! $response->ok()) {
            return [];
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    private function endpoint(string $path): string
    {
        return rtrim(self::BASE_URL, '/').'/'.ltrim($path, '/');
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return is_string($value) ? $value : '';
    }

    private function flushTotalCountCache(): void
    {
        foreach (['wilayah:total:provinces', 'wilayah:total:regencies', 'wilayah:total:districts', 'wilayah:total:villages'] as $cacheKey) {
            Cache::forget($cacheKey);
        }
    }

    private function putProgress(array $updates): void
    {
        $current = Cache::get(self::PROGRESS_CACHE_KEY, [
            'running' => false,
            'progress' => 0,
            'stage' => 'idle',
            'message' => 'Belum ada sync berjalan.',
            'processed' => 0,
            'total' => 0,
            'error' => null,
            'summary' => null,
            'startedAt' => null,
            'updatedAt' => null,
            'finishedAt' => null,
        ]);

        $next = array_merge($current, $updates);
        $next['updatedAt'] = Carbon::now()->toIso8601String();

        Cache::put(self::PROGRESS_CACHE_KEY, $next, self::PROGRESS_CACHE_TTL_SECONDS);
    }
}