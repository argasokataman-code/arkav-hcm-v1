<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 120)->unique();
                $table->string('name', 200);
                $table->string('legal_name', 255)->nullable();
                $table->string('status', 50)->default('active')->index();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('timezone', 64)->default('UTC');
                $table->string('currency', 3)->default('IDR');
                $table->string('country_code', 2)->default('ID');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_users')) {
            Schema::create('company_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 50)->default('member')->index();
                $table->string('status', 50)->default('active')->index();
                $table->timestamp('joined_at')->nullable();
                $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'user_id'], 'company_users_unique_membership');
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('plan_code', 120)->default('starter');
                $table->string('status', 50)->default('trial')->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->boolean('auto_renew')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'subscriptions_company_status_idx');
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('IDR');
                $table->string('status', 50)->default('pending')->index();
                $table->string('gateway', 100)->nullable();
                $table->string('gateway_reference', 191)->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'payments_company_status_idx');
            });
        }

        if (! Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('key', 150);
                $table->text('value')->nullable();
                $table->string('type', 50)->default('string');
                $table->timestamps();

                $table->unique(['company_id', 'key'], 'company_settings_unique_key_per_company');
            });
        }

        $this->backfillDefaultCompany();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');
        Schema::enableForeignKeyConstraints();
    }

    private function backfillDefaultCompany(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('companies') || ! Schema::hasTable('company_users')) {
            return;
        }

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        $company = DB::table('companies')
            ->where('code', 'default_company')
            ->first();

        if (! $company) {
            $companyId = DB::table('companies')->insertGetId([
                'code' => 'default_company',
                'name' => 'Default Company',
                'legal_name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => $firstUserId,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'currency' => 'IDR',
                'country_code' => 'ID',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $companyId = (int) $company->id;
        }

        DB::table('users')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($users) use ($companyId, $firstUserId): void {
                foreach ($users as $user) {
                    $exists = DB::table('company_users')
                        ->where('company_id', $companyId)
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('company_users')->insert([
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'role' => (int) $user->id === (int) $firstUserId ? 'owner' : 'admin',
                        'status' => 'active',
                        'joined_at' => now(),
                        'invited_by_user_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }
};
