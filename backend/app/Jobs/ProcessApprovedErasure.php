<?php

namespace App\Jobs;

use App\Models\AiChatLog;
use App\Models\AttendanceRecord;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\ErasureRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessApprovedErasure implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $erasureRequestId) {}

    public function handle(): void
    {
        /** @var ErasureRequest|null $req */
        $req = ErasureRequest::query()->find($this->erasureRequestId);

        if (! $req || $req->status !== 'approved') {
            return;
        }

        $subjectUuid = $req->subject_uuid;
        $companyId = $req->company_id;

        $user = User::query()->where('uuid', $subjectUuid)->first();
        if (! $user) {
            $this->markCompleted($req);

            return;
        }

        DB::transaction(function () use ($user, $companyId, $req): void {
            // Soft-delete employee profile and related records
            $profile = EmployeeProfile::query()
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->first();

            if ($profile) {
                // Anonymize sensitive PII before soft delete (pseudonymization)
                $profile->fill([
                    'nik' => null,
                    'phone' => null,
                    'address' => null,
                    'place_of_birth' => null,
                    'date_of_birth' => null,
                    'gender' => null,
                    'marital_status' => null,
                    'religion' => null,
                    'nationality' => null,
                    'bank_name' => null,
                    'bank_account_no' => null,
                    'bank_ifsc_code' => null,
                    'bank_branch' => null,
                    'emergency_contacts' => null,
                    'education_items' => null,
                    'experience_items' => null,
                    'bio' => null,
                ])->save();

                // Soft-delete tax profiles and benefits
                EmployeeTaxProfile::query()->where('employee_id', $profile->id)->delete();
                EmployeeBenefit::query()->where('employee_id', $profile->id)->delete();

                // Soft-delete biometric consent
                EmployeeBiometricConsent::query()
                    ->where('employee_uuid', (string) $profile->uuid)
                    ->where('company_id', $companyId)
                    ->update(['consent_withdrawn_at' => now()]);

                $profile->delete(); // soft delete
            }

            // Soft-delete AI chat logs for this user in this company
            AiChatLog::query()
                ->where('user_legacy_id', $user->id)
                ->where('company_id', $companyId)
                ->delete();

            // Soft-delete attendance records
            AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->delete();

            // Anonymize user's PII (do not hard-delete the user record for audit trail integrity)
            $user->update([
                'name' => 'Anonymized User',
                'email' => 'anonymized_'.$user->id.'@erased.local',
            ]);

            $this->markCompleted($req);

            Log::info('PDP erasure completed', [
                'erasure_request_id' => $req->id,
                'subject_uuid' => $req->subject_uuid,
                'company_id' => $companyId,
            ]);
        });
    }

    private function markCompleted(ErasureRequest $req): void
    {
        $req->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
