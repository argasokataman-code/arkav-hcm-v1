<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchase_transactions MODIFY COLUMN status ENUM('draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled', 'expired', 'consolidated') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchase_transactions MODIFY COLUMN status ENUM('draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft'");
    }
};
