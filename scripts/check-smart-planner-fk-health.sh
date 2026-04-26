#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$PROJECT_ROOT/backend"

cd "$BACKEND_DIR"

TMP_PHP="$(mktemp)"
trap 'rm -f "$TMP_PHP"' EXIT

cat > "$TMP_PHP" <<'PHP'
<?php

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('hcm_smart_planner_settings')) {
    echo 'check-smart-planner-fk-health: skipped (table hcm_smart_planner_settings not found).'.PHP_EOL;
    exit(0);
}

if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
    echo 'check-smart-planner-fk-health: skipped (non-MySQL driver).'.PHP_EOL;
    exit(0);
}

$checks = [
    ['column' => 'company_uuid', 'parent_table' => 'companies', 'parent_column' => 'uuid'],
    ['column' => 'created_by_user_uuid', 'parent_table' => 'users', 'parent_column' => 'uuid'],
    ['column' => 'updated_by_user_uuid', 'parent_table' => 'users', 'parent_column' => 'uuid'],
];

$missingColumns = [];
$missingForeigns = [];

foreach ($checks as $check) {
    if (! Schema::hasColumn('hcm_smart_planner_settings', $check['column'])) {
        $missingColumns[] = $check['column'];
        continue;
    }

    $count = DB::table('information_schema.KEY_COLUMN_USAGE')
        ->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->where('TABLE_NAME', 'hcm_smart_planner_settings')
        ->where('COLUMN_NAME', $check['column'])
        ->where('REFERENCED_TABLE_NAME', $check['parent_table'])
        ->where('REFERENCED_COLUMN_NAME', $check['parent_column'])
        ->count();

    if ((int) $count === 0) {
        $missingForeigns[] = $check['column'].' -> '.$check['parent_table'].'.'.$check['parent_column'];
    }
}

if (! empty($missingColumns) || ! empty($missingForeigns)) {
    if (! empty($missingColumns)) {
        fwrite(STDERR, 'check-smart-planner-fk-health: missing UUID columns: '.implode(', ', $missingColumns).PHP_EOL);
    }
    if (! empty($missingForeigns)) {
        fwrite(STDERR, 'check-smart-planner-fk-health: missing UUID foreign keys: '.implode('; ', $missingForeigns).PHP_EOL);
    }

    exit(1);
}

echo 'check-smart-planner-fk-health: OK'.PHP_EOL;
PHP

php "$TMP_PHP"
