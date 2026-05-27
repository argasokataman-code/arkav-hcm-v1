<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create package_addon_assignments pivot table.
 *
 * This table defines which add-ons are available for purchase by tenants on a
 * given package. The add-on catalog on the /upgrade page must only show add-ons
 * that the global admin has explicitly assigned to the tenant's active package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_addon_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('package_uuid', 36);
            $table->unsignedBigInteger('package_addon_id');
            $table->timestamps();

            $table->unique(['package_uuid', 'package_addon_id'], 'unique_pkg_addon');
            $table->foreign('package_uuid')
                ->references('uuid')->on('packages')
                ->cascadeOnDelete();
            $table->foreign('package_addon_id')
                ->references('id')->on('package_addons')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_addon_assignments');
    }
};
