<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['department_id', 'name']);
            });
        }

        if (Schema::hasTable('employee_employment_history') && ! Schema::hasColumn('employee_employment_history', 'probation_end_date')) {
            Schema::table('employee_employment_history', function (Blueprint $table): void {
                $table->date('probation_end_date')->nullable()->after('end_date');
            });
        }

        if (Schema::hasTable('employee_assignments') && ! Schema::hasColumn('employee_assignments', 'team_id')) {
            Schema::table('employee_assignments', function (Blueprint $table): void {
                $table->foreignId('team_id')->nullable()->after('designation_id')->constrained('teams')->nullOnDelete();
                $table->index(['employee_id', 'team_id']);
            });
        }

        $this->backfillTeams();
        $this->normalizeContracts();
        $this->collapseTaxProfilesToSingleIndonesiaStatus();

        // Legacy tax history columns are intentionally left in place for backward-compatible migrations.
        // Runtime code now enforces a single Indonesia-only tax classification row per employee.
    }

    public function down(): void
    {

        if (Schema::hasTable('employee_assignments') && Schema::hasColumn('employee_assignments', 'team_id')) {
            Schema::table('employee_assignments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        if (Schema::hasTable('employee_employment_history') && Schema::hasColumn('employee_employment_history', 'probation_end_date')) {
            Schema::table('employee_employment_history', function (Blueprint $table): void {
                $table->dropColumn('probation_end_date');
            });
        }

        Schema::dropIfExists('teams');
    }

    private function backfillTeams(): void
    {
        if (! Schema::hasTable('teams') || ! Schema::hasTable('employee_assignments')) {
            return;
        }

        $rows = DB::table('employee_assignments')
            ->select('id', 'department_id', 'team_name')
            ->whereNotNull('team_name')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) ($row->team_name ?? ''));
            if ($name === '') {
                continue;
            }

            $teamId = DB::table('teams')
                ->where('department_id', $row->department_id)
                ->where('name', $name)
                ->value('id');

            if (! $teamId) {
                $teamId = DB::table('teams')->insertGetId([
                    'department_id' => $row->department_id,
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employee_assignments')
                ->where('id', $row->id)
                ->update(['team_id' => $teamId]);
        }
    }

    private function normalizeContracts(): void
    {
        if (! Schema::hasTable('employee_contracts')) {
            return;
        }

        $rows = DB::table('employee_contracts')->select('id', 'contract_type', 'status', 'end_date')->get();
        foreach ($rows as $row) {
            $type = strtolower(trim((string) ($row->contract_type ?? '')));
            if ($type === '' || $type === 'pkwtt') {
                $type = 'permanent';
            }
            if ($type === 'pkwt') {
                $type = 'contract';
            }
            if (! in_array($type, ['contract', 'permanent'], true)) {
                $type = 'permanent';
            }

            $status = strtolower(trim((string) ($row->status ?? '')));
            if ($status === 'scheduled_end') {
                $status = ($row->end_date !== null && (string) $row->end_date < now()->toDateString()) ? 'ended' : 'active';
            }
            if (! in_array($status, ['active', 'ended', 'terminated'], true)) {
                $status = ($row->end_date !== null && (string) $row->end_date < now()->toDateString()) ? 'ended' : 'active';
            }

            DB::table('employee_contracts')->where('id', $row->id)->update([
                'contract_type' => $type,
                'status' => $status,
            ]);
        }
    }

    private function collapseTaxProfilesToSingleIndonesiaStatus(): void
    {
        if (! Schema::hasTable('employee_tax_profiles')) {
            return;
        }

        $latestIds = DB::table('employee_tax_profiles')
            ->selectRaw('MAX(id) as id')
            ->groupBy('employee_id')
            ->pluck('id')
            ->filter()
            ->values();

        if ($latestIds->isNotEmpty()) {
            DB::table('employee_tax_profiles')->whereNotIn('id', $latestIds->all())->delete();
        }

        $rows = DB::table('employee_tax_profiles')->select('id', 'tax_status', 'ptkp_status')->get();
        foreach ($rows as $row) {
            $normalized = $this->normalizeTaxStatus($row->ptkp_status ?: $row->tax_status);
            DB::table('employee_tax_profiles')->where('id', $row->id)->update([
                'tax_status' => $normalized,
                'ptkp_status' => $normalized,
            ]);
        }
    }

    private function normalizeTaxStatus(?string $value): string
    {
        $raw = strtoupper(str_replace(['/', ' '], '', trim((string) $value)));
        return match ($raw) {
            'TK', 'TK0', '' => 'TK0',
            'TK1' => 'TK1',
            'TK2' => 'TK2',
            'TK3' => 'TK3',
            'K', 'K0' => 'K0',
            'K1' => 'K1',
            'K2' => 'K2',
            'K3' => 'K3',
            default => 'TK0',
        };
    }
};
