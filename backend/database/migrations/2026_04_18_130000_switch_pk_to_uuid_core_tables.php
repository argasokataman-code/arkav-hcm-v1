<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disabled for now - PK switch to UUID is too complex with existing FKs.
        // Strategy: Keep integer id as PK, use uuid as secondary index for API/routing.
        // This is safer and does not break existing FK relationships.
    }

    public function down(): void
    {
        // No-op
    }
};
