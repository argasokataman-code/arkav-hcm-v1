#!/usr/bin/env bash
set -euo pipefail

# Verifies DB table connectivity by foreign keys.
# Fails when isolated tables exist outside the expected system/global allowlist.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"

if [[ ! -f "${BACKEND_DIR}/artisan" ]]; then
  echo "[fk-isolation] backend/artisan not found"
  exit 1
fi

cd "${BACKEND_DIR}"

php -r '
require getcwd()."/vendor/autoload.php";
$app = require getcwd()."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = Illuminate\Support\Facades\DB::getDatabaseName();

$tables = collect(Illuminate\Support\Facades\DB::select(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?",
    [$database]
))->pluck("TABLE_NAME");

$fkFrom = collect(Illuminate\Support\Facades\DB::select(
    "SELECT DISTINCT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
    [$database]
))->pluck("TABLE_NAME");

$fkTo = collect(Illuminate\Support\Facades\DB::select(
    "SELECT DISTINCT REFERENCED_TABLE_NAME AS TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
    [$database]
))->pluck("TABLE_NAME");

$connected = $fkFrom->merge($fkTo)->unique()->values();
$isolated = $tables->diff($connected)->sort()->values();

$allowlist = collect([
    "cache",
    "cache_locks",
    "failed_jobs",
    "hcm_salary_component_categories",
    "job_batches",
    "jobs",
    "migrations",
    "password_reset_tokens",
    "sessions",
    "settings",
]);

$unexpected = $isolated->diff($allowlist)->values();

echo "[fk-isolation] database={$database}\n";
echo "[fk-isolation] total_tables=".$tables->count()."\n";
echo "[fk-isolation] isolated_tables=".$isolated->count()."\n";
echo "[fk-isolation] isolated_list=".$isolated->implode(",")."\n";

if ($unexpected->isNotEmpty()) {
    echo "[fk-isolation] unexpected_isolated=".$unexpected->implode(",")."\n";
    exit(1);
}

echo "[fk-isolation] status=PASS\n";
'