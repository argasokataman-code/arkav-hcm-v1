<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\PerformanceCycle;
use App\Models\PerformanceIndicatorItem;
use App\Models\PerformanceIndicatorTemplate;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalType;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewScore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // "Bersih" + idempotent: hapus data performance dulu supaya seed konsisten.
            DB::table('performance_review_scores')->delete();
            DB::table('performance_reviews')->delete();
            DB::table('performance_indicator_items')->delete();
            DB::table('performance_indicator_templates')->delete();
            DB::table('performance_cycles')->delete();
            DB::table('performance_goals')->delete();
            DB::table('performance_goal_types')->delete();

            // Users
            $admin = User::query()->updateOrCreate(
                ['email' => 'perf.admin@example.com'],
                ['name' => 'Performance Admin', 'password' => Hash::make('StrongPass1')]
            );
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $admin->id],
                array_merge(
                    ['team' => 'HR', 'designation' => 'HR Admin'],
                    $this->demoBankForUserId((int) $admin->id)
                )
            );

            $manager = User::query()->updateOrCreate(
                ['email' => 'perf.manager@example.com'],
                ['name' => 'Performance Manager', 'password' => Hash::make('StrongPass1')]
            );
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $manager->id],
                array_merge(
                    ['team' => 'Engineering', 'designation' => 'Manager'],
                    $this->demoBankForUserId((int) $manager->id)
                )
            );

            $employeeA = User::query()->updateOrCreate(
                ['email' => 'perf.employee.a@example.com'],
                ['name' => 'Performance Employee A', 'password' => Hash::make('StrongPass1')]
            );
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $employeeA->id],
                array_merge(
                    ['team' => 'Engineering', 'designation' => 'Staff', 'manager_user_id' => $manager->id],
                    $this->demoBankForUserId((int) $employeeA->id)
                )
            );

            $employeeB = User::query()->updateOrCreate(
                ['email' => 'perf.employee.b@example.com'],
                ['name' => 'Performance Employee B', 'password' => Hash::make('StrongPass1')]
            );
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $employeeB->id],
                array_merge(
                    ['team' => 'Engineering', 'designation' => 'Staff', 'manager_user_id' => $manager->id],
                    $this->demoBankForUserId((int) $employeeB->id)
                )
            );

            // Cycle
            $cycle = PerformanceCycle::query()->create([
                'name' => '2026 H1',
                'period_start' => '2026-01-01',
                'period_end' => '2026-06-30',
                'status' => 'active',
            ]);

            // Templates
            $tplEng = PerformanceIndicatorTemplate::query()->create([
                'name' => 'Engineering - IC',
                'department' => 'Engineering',
                'designation' => 'Software Engineer',
                'is_active' => true,
            ]);

            $tplHr = PerformanceIndicatorTemplate::query()->create([
                'name' => 'HR - Generalist',
                'department' => 'HR',
                'designation' => 'HR Staff',
                'is_active' => true,
            ]);

            // Items (Engineering)
            $itemsEng = [
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplEng->id,
                    'section' => 'kpi',
                    'title' => 'Delivery & Quality',
                    'description' => 'Kualitas output dan ketepatan delivery.',
                    'weight' => 60,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 10,
                ]),
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplEng->id,
                    'section' => 'kpi',
                    'title' => 'Reliability',
                    'description' => 'Stabilitas, incident, dan operasional.',
                    'weight' => 40,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 20,
                ]),
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplEng->id,
                    'section' => 'behavioral',
                    'title' => 'Collaboration',
                    'description' => 'Komunikasi dan kolaborasi lintas tim.',
                    'weight' => null,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 30,
                ]),
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplEng->id,
                    'section' => 'behavioral',
                    'title' => 'Ownership',
                    'description' => 'Inisiatif dan tanggung jawab.',
                    'weight' => null,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 40,
                ]),
            ];

            // Items (HR)
            $itemsHr = [
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplHr->id,
                    'section' => 'kpi',
                    'title' => 'Service Quality',
                    'description' => 'Kualitas layanan HR ke employee.',
                    'weight' => 70,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 10,
                ]),
                PerformanceIndicatorItem::query()->create([
                    'template_id' => $tplHr->id,
                    'section' => 'behavioral',
                    'title' => 'Integrity',
                    'description' => 'Kepatuhan & integritas.',
                    'weight' => null,
                    'rating_scale_min' => 1,
                    'rating_scale_max' => 5,
                    'sort_order' => 20,
                ]),
            ];

            // Reviews + score rows
            $this->seedReview($cycle, $tplEng, $itemsEng, $employeeA, $manager, 'draft');
            $this->seedReview($cycle, $tplEng, $itemsEng, $employeeB, $manager, 'submitted');
            $this->seedReview($cycle, $tplHr, $itemsHr, $admin, null, 'finalized', true);

            // Convenience for local dev:
            // If there are existing users outside demo accounts, create a draft review for the first one
            // so `/performance-review` scope=me won't be empty after seeding.
            $existing = User::query()
                ->whereNotIn('email', [
                    'perf.admin@example.com',
                    'perf.manager@example.com',
                    'perf.employee.a@example.com',
                    'perf.employee.b@example.com',
                ])
                ->orderBy('id')
                ->first();

            if ($existing) {
                EmployeeProfile::query()->updateOrCreate(
                    ['user_id' => $existing->id],
                    array_merge(
                        ['team' => 'Engineering', 'designation' => 'Staff', 'manager_user_id' => $manager->id],
                        $this->demoBankForUserId((int) $existing->id)
                    )
                );

                $this->seedReview($cycle, $tplEng, $itemsEng, $existing, $manager, 'draft');
            }

            // Goal types
            $goalTypeDev = PerformanceGoalType::query()->create([
                'name' => 'Development',
                'description' => 'Personal development goals.',
                'is_active' => true,
            ]);
            $goalTypeKpi = PerformanceGoalType::query()->create([
                'name' => 'KPI',
                'description' => 'Work goals tied to KPIs.',
                'is_active' => true,
            ]);
            $goalTypeArchived = PerformanceGoalType::query()->create([
                'name' => 'Archived',
                'description' => 'Example inactive type.',
                'is_active' => false,
            ]);

            // Goals (employee self + team)
            PerformanceGoal::query()->create([
                'goal_type_id' => $goalTypeDev->id,
                'user_id' => $employeeA->id,
                'manager_user_id' => $manager->id,
                'subject' => 'Improve programming skills',
                'target_achievement' => 'Complete HTML course',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-01',
                'description' => 'Demo goal for Goal Tracking.',
                'status' => 'active',
                'progress_percent' => 25,
            ]);

            PerformanceGoal::query()->create([
                'goal_type_id' => $goalTypeKpi->id,
                'user_id' => $employeeB->id,
                'manager_user_id' => $manager->id,
                'subject' => 'Reduce incident response time',
                'target_achievement' => 'MTTR < 30 minutes',
                'start_date' => '2026-01-15',
                'end_date' => '2026-06-15',
                'description' => null,
                'status' => 'active',
                'progress_percent' => 40,
            ]);

            // Admin can have a self goal too (no manager).
            PerformanceGoal::query()->create([
                'goal_type_id' => $goalTypeDev->id,
                'user_id' => $admin->id,
                'manager_user_id' => null,
                'subject' => 'Improve leadership skills',
                'target_achievement' => 'Complete leadership course',
                'start_date' => '2026-02-01',
                'end_date' => '2026-05-01',
                'description' => 'Demo admin goal.',
                'status' => 'completed',
                'progress_percent' => 100,
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    private function demoBankForUserId(int $userId): array
    {
        return [
            'bank_name' => 'Bank Central Asia',
            'bank_account_no' => sprintf('5271%08d', $userId),
            'bank_ifsc_code' => 'BCA001',
            'bank_branch' => 'Jakarta',
        ];
    }

    private function seedReview(
        PerformanceCycle $cycle,
        PerformanceIndicatorTemplate $tpl,
        array $items,
        User $employee,
        ?User $manager,
        string $status,
        bool $asAdminSelf = false
    ): void {
        $review = PerformanceReview::query()->create([
            'cycle_id' => $cycle->id,
            'user_id' => $employee->id,
            'manager_user_id' => $manager?->id,
            'template_id' => $tpl->id,
            'status' => $status,
            'self_note' => 'Self: contoh catatan singkat.',
            'manager_note' => $manager ? 'Manager: catatan review.' : null,
            'final_note' => $asAdminSelf ? 'Admin: final note contoh.' : null,
        ]);

        foreach ($items as $it) {
            $selfScore = $it->section === 'behavioral' ? 4 : 80;
            $mgrScore = $it->section === 'behavioral' ? 5 : 85;
            $finalScore = $it->section === 'behavioral' ? 5 : 90;

            PerformanceReviewScore::query()->create([
                'review_id' => $review->id,
                'item_id' => $it->id,
                'self_score' => in_array($status, ['draft', 'submitted', 'manager_reviewed', 'finalized'], true) ? $selfScore : null,
                'manager_score' => in_array($status, ['manager_reviewed', 'finalized'], true) ? $mgrScore : null,
                'final_score' => $status === 'finalized' ? $finalScore : null,
                'self_comment' => 'Self comment',
                'manager_comment' => $manager ? 'Manager comment' : null,
                'final_comment' => $status === 'finalized' ? 'Final comment' : null,
            ]);
        }

        // Hitung totals via controller logic? Simpel: biarkan dihitung saat user save via UI.
        // Untuk seed, set nilai kasar agar list ada angka.
        $review->self_total_score = 80;
        if (in_array($status, ['manager_reviewed', 'finalized'], true)) {
            $review->manager_total_score = 85;
        }
        if ($status === 'finalized') {
            $review->final_total_score = 90;
        }
        $review->save();
    }
}

