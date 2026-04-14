<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('code', 80);
            $table->string('name', 150);
            $table->string('description', 2000)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'hcm_roles_company_code_unique');
            $table->index(['company_id', 'status'], 'hcm_roles_company_status_idx');
        });

        Schema::create('hcm_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('module', 80)->index();
            $table->string('resource', 80)->index();
            $table->string('action', 80)->index();
            $table->string('name', 150);
            $table->string('description', 2000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['module', 'resource', 'action'], 'hcm_permissions_mra_idx');
        });

        Schema::create('hcm_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('hcm_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('hcm_permissions')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['role_id', 'permission_id'], 'hcm_role_permissions_unique');
        });

        Schema::create('hcm_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('hcm_roles')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'company_id', 'status'], 'hcm_user_roles_lookup_idx');
            $table->index(['role_id', 'status'], 'hcm_user_roles_role_status_idx');
        });

        Schema::create('hcm_user_role_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('hcm_roles')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_user_id', 'created_at'], 'hcm_user_role_audits_target_created_idx');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('hcm_user_role_audits');
        Schema::dropIfExists('hcm_user_roles');
        Schema::dropIfExists('hcm_role_permissions');
        Schema::dropIfExists('hcm_permissions');
        Schema::dropIfExists('hcm_roles');
        Schema::enableForeignKeyConstraints();
    }
};
