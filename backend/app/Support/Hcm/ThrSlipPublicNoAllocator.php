<?php

namespace App\Support\Hcm;

use App\Models\HcmThrBatchLine;
use Illuminate\Support\Str;

/**
 * Alokasi `hcm_thr_batch_lines.thr_slip_public_no` unik (THR-{tahun}-{ULID}).
 */
final class ThrSlipPublicNoAllocator
{
    public static function allocate(int $calendarYear): string
    {
        $prefix = 'THR-'.$calendarYear.'-';

        for ($i = 0; $i < 48; $i++) {
            $candidate = $prefix.strtoupper((string) Str::ulid());
            if (! HcmThrBatchLine::query()->where('thr_slip_public_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('THR_SLIP_PUBLIC_NO_ALLOCATION_FAILED');
    }
}
