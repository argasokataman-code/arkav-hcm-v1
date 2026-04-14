<?php

namespace App\Http\Controllers;

use App\Console\Commands\WilayahSyncCommand;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

class WilayahLocationController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    private const CACHE_TTL_SECONDS = 300;

    public function sync(): RedirectResponse
    {
        try {
            if (app()->runningUnitTests()) {
                Artisan::call('wilayah:sync');
                $output = trim((string) Artisan::output());

                return redirect()->back()->with('wilayahSyncStatus', [
                    'type' => 'success',
                    'message' => 'Manual sync berhasil dijalankan.',
                    'output' => $output,
                ]);
            }

            $process = new Process([
                PHP_BINARY,
                base_path('artisan'),
                WilayahSyncCommand::NAME,
                '--isolated',
            ]);
            $process->disableOutput();
            $process->start();

            return redirect()->back()->with('wilayahSyncStatus', [
                'type' => 'success',
                'message' => 'Manual sync dimulai di background. Silakan refresh halaman beberapa saat lagi.',
                'output' => null,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            return redirect()->back()->with('wilayahSyncStatus', [
                'type' => 'warning',
                'message' => 'Sync belum berhasil diproses. Silakan coba lagi beberapa saat lagi.',
                'output' => null,
            ]);
        }
    }

    public function countries(Request $request): View
    {
        [$q, $perPage] = $this->resolveFilters($request);

        $rows = WilayahProvince::query()
            ->select(['id', 'code', 'name', 'updated_at'])
            ->withCount('regencies')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $term = '%'.$q.'%';
                    $codeTerm = $q.'%';
                    $search->where('code', 'like', $codeTerm)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->orderBy('code')
            ->simplePaginate($perPage)
            ->withQueryString();

        return view('locations.countries', [
            'pageTitle' => 'Provinces',
            'pageSubtitle' => 'Synced from wilayah.id and stored locally in the wilayah_provinces table.',
            'rows' => $rows,
            'totalCount' => $this->cachedTotal('wilayah:total:provinces', fn (): int => (int) WilayahProvince::query()->count()),
            'totalLabel' => 'provinces',
            'countLabel' => 'regencies_count',
            'parentLabel' => null,
            'filters' => ['q' => $q, 'perPage' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function states(Request $request): View
    {
        [$q, $perPage] = $this->resolveFilters($request);

        $rows = WilayahRegency::query()
            ->select(['id', 'province_id', 'code', 'name', 'updated_at'])
            ->with('province:id,code,name')
            ->withCount('districts')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $term = '%'.$q.'%';
                    $codeTerm = $q.'%';
                    $search->where('code', 'like', $codeTerm)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('province', function ($provinceQuery) use ($q) {
                            $provinceQuery->where('name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->orderBy('code')
            ->simplePaginate($perPage)
            ->withQueryString();

        return view('locations.states', [
            'pageTitle' => 'Regencies / Cities',
            'pageSubtitle' => 'Kabupaten dan kota diambil dari wilayah.id lalu disimpan di database lokal.',
            'rows' => $rows,
            'totalCount' => $this->cachedTotal('wilayah:total:regencies', fn (): int => (int) WilayahRegency::query()->count()),
            'totalLabel' => 'regencies',
            'countLabel' => 'districts_count',
            'parentLabel' => 'province',
            'filters' => ['q' => $q, 'perPage' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function cities(Request $request): View
    {
        [$q, $perPage] = $this->resolveFilters($request);

        $rows = WilayahDistrict::query()
            ->select(['id', 'regency_id', 'code', 'name', 'updated_at'])
            ->with(['regency:id,province_id,code,name', 'regency.province:id,code,name'])
            ->withCount('villages')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $term = '%'.$q.'%';
                    $codeTerm = $q.'%';
                    $search->where('code', 'like', $codeTerm)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('regency', function ($regencyQuery) use ($q) {
                            $regencyQuery->where('name', 'like', '%'.$q.'%')
                                ->orWhereHas('province', function ($provinceQuery) use ($q) {
                                    $provinceQuery->where('name', 'like', '%'.$q.'%');
                                });
                        });
                });
            })
            ->orderBy('code')
            ->simplePaginate($perPage)
            ->withQueryString();

        return view('locations.cities', [
            'pageTitle' => 'Districts / Subdistricts',
            'pageSubtitle' => 'Kecamatan disinkronkan otomatis dari wilayah.id; data desa juga tersimpan di backend.',
            'rows' => $rows,
            'totalCount' => $this->cachedTotal('wilayah:total:districts', fn (): int => (int) WilayahDistrict::query()->count()),
            'totalLabel' => 'districts',
            'countLabel' => 'villages_count',
            'parentLabel' => 'regency',
            'filters' => ['q' => $q, 'perPage' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function villages(Request $request): View
    {
        [$q, $perPage] = $this->resolveFilters($request);

        $rows = WilayahVillage::query()
            ->select(['id', 'district_id', 'code', 'name', 'updated_at'])
            ->with([
                'district:id,regency_id,code,name',
                'district.regency:id,province_id,code,name',
                'district.regency.province:id,code,name',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $term = '%'.$q.'%';
                    $codeTerm = $q.'%';
                    $search->where('code', 'like', $codeTerm)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('district', function ($districtQuery) use ($q) {
                            $districtQuery->where('name', 'like', '%'.$q.'%')
                                ->orWhereHas('regency', function ($regencyQuery) use ($q) {
                                    $regencyQuery->where('name', 'like', '%'.$q.'%')
                                        ->orWhereHas('province', function ($provinceQuery) use ($q) {
                                            $provinceQuery->where('name', 'like', '%'.$q.'%');
                                        });
                                });
                        });
                });
            })
            ->orderBy('code')
            ->simplePaginate($perPage)
            ->withQueryString();

        return view('locations.villages', [
            'pageTitle' => 'Villages / Subvillages',
            'pageSubtitle' => 'Desa dan kelurahan disinkronkan dari wilayah.id dan terhubung sampai level district, regency, dan province.',
            'rows' => $rows,
            'totalCount' => $this->cachedTotal('wilayah:total:villages', fn (): int => (int) WilayahVillage::query()->count()),
            'totalLabel' => 'villages',
            'countLabel' => null,
            'parentLabel' => 'district',
            'filters' => ['q' => $q, 'perPage' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveFilters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            $q = '';
        }

        $perPage = (int) $request->integer('perPage', self::PER_PAGE_OPTIONS[0]);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE_OPTIONS[0];
        }

        return [$q, $perPage];
    }

    private function cachedTotal(string $cacheKey, callable $resolver): int
    {
        return (int) Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, $resolver);
    }
}