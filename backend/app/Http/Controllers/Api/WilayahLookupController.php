<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WilayahLookupController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => WilayahProvince::query()
                ->select(['id', 'code', 'name'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function regencies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provinceId' => ['required', 'integer', 'exists:wilayah_provinces,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => WilayahRegency::query()
                ->select(['id', 'province_id', 'code', 'name'])
                ->where('province_id', $validated['provinceId'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regencyId' => ['required', 'integer', 'exists:wilayah_regencies,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => WilayahDistrict::query()
                ->select(['id', 'regency_id', 'code', 'name'])
                ->where('regency_id', $validated['regencyId'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function villages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'districtId' => ['required', 'integer', 'exists:wilayah_districts,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => WilayahVillage::query()
                ->select(['id', 'district_id', 'code', 'name'])
                ->where('district_id', $validated['districtId'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}