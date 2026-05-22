<?php

namespace App\Contracts\Hcm;

/**
 * Abstraksi disbursement THR ke payment gateway (Midtrans Iris, dll.).
 * Implementasi stub memakai config {@see config('hcm.thr_disbursement_fail_user_ids')}.
 */
interface ThrDisbursementGatewayInterface
{
    /**
     * @param  list<array{userId: int, amount: float, bankAccountNo: ?string, bankName: ?string, recipientName: string}>  $items
     * @return list<array{userId: int, status: string, ref: ?string, failureReason: ?string}> status = success|failed
     */
    public function disburseBatch(array $items): array;
}
