<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'hcm_salary_components_kind_category_name_unique';

    public function up(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        $this->normalizeDuplicateNames();

        Schema::table('hcm_salary_components', function (Blueprint $table): void {
            $table->unique(['kind', 'category', 'name'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        Schema::table('hcm_salary_components', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }

    private function normalizeDuplicateNames(): void
    {
        $rows = DB::table('hcm_salary_components')
            ->select('id', 'kind', 'category', 'name')
            ->orderBy('kind')
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $seen = [];

        foreach ($rows as $row) {
            $baseName = trim((string) $row->name);
            if ($baseName === '') {
                $baseName = 'Salary Component';
            }

            $groupKey = strtolower($row->kind.'|'.$row->category.'|'.$baseName);

            if (! isset($seen[$groupKey])) {
                $seen[$groupKey] = 1;
                if ($baseName !== (string) $row->name) {
                    DB::table('hcm_salary_components')->where('id', $row->id)->update([
                        'name' => $baseName,
                    ]);
                }
                continue;
            }

            $seen[$groupKey]++;
            $suffix = ' (#'.$row->id.')';
            $maxBaseLen = 200 - strlen($suffix);
            $candidate = substr($baseName, 0, max(1, $maxBaseLen)).$suffix;

            $counter = 2;
            while ($this->nameExistsInGroup((string) $row->kind, (string) $row->category, $candidate, (int) $row->id)) {
                $counterSuffix = ' (#'.$row->id.'-'.$counter.')';
                $maxCounterBaseLen = 200 - strlen($counterSuffix);
                $candidate = substr($baseName, 0, max(1, $maxCounterBaseLen)).$counterSuffix;
                $counter++;
            }

            DB::table('hcm_salary_components')->where('id', $row->id)->update([
                'name' => $candidate,
            ]);
        }
    }

    private function nameExistsInGroup(string $kind, string $category, string $name, int $ignoreId): bool
    {
        return DB::table('hcm_salary_components')
            ->where('kind', $kind)
            ->where('category', $category)
            ->where('name', $name)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }
};
