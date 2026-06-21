<?php

namespace App\Services\Hcm;

use App\Contracts\Hcm\ThrDisbursementGatewayInterface;

final class StubThrDisbursementGateway implements ThrDisbursementGatewayInterface
{
    public function disburseBatch(array $items): array
    {
        $failIds = config('hcm.thr_disbursement_fail_user_ids', []);
        $out = [];
        foreach ($items as $row) {
            $uid = (int) $row['userId'];
            $bank = trim((string) ($row['bankAccountNo'] ?? ''));
            if (in_array($uid, $failIds, true)) {
                $out[] = [
                    'userId' => $uid,
                    'status' => 'failed',
                    'ref' => null,
                    'failureReason' => 'Stub: user ID termasuk daftar gagal (HCM_THR_DISBURSEMENT_FAIL_USER_IDS).',
                ];

                continue;
            }
            if ($bank === '') {
                $out[] = [
                    'userId' => $uid,
                    'status' => 'failed',
                    'ref' => null,
                    'failureReason' => 'Rekening bank belum diisi di profil karyawan.',
                ];

                continue;
            }
            $ref = 'stub-thr-'.$uid.'-'.time();
            $out[] = [
                'userId' => $uid,
                'status' => 'success',
                'ref' => $ref,
                'failureReason' => null,
            ];
        }

        return $out;
    }
}
