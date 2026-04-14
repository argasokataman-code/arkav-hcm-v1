<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAttachment;
use App\Models\AssetLog;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AssetService
{
    public const FEATURE_ASSET_MANAGEMENT = 'asset_management';
    public const FEATURE_ASSET_LOGS = 'asset_logs';
    public const FEATURE_ASSET_ATTACHMENTS = 'asset_attachments';
    public const FEATURE_ASSET_WARRANTY = 'asset_warranty';
    public const FEATURE_ASSET_MAINTENANCE = 'asset_maintenance';
    public const FEATURE_ASSET_REPORTING = 'asset_reporting';
    public const FEATURE_ASSET_DEPRECIATION = 'asset_depreciation';

    public function companyHasFeature(int $companyId, string $featureCode): bool
    {
        $subscription = Subscription::query()
            ->with(['package.features'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->latest('starts_at')
            ->first();

        if (! $subscription || ! $subscription->package) {
            return false;
        }

        return $subscription->package->hasFeature($featureCode);
    }

    public function ensureCompanyHasFeature(int $companyId, string $featureCode): void
    {
        if (! $this->companyHasFeature($companyId, $featureCode)) {
            throw new RuntimeException("Feature {$featureCode} is not enabled for this company.");
        }
    }

    public function generateAssetCode(int $companyId): string
    {
        $company = Company::query()->find($companyId);
        $companyCode = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($company?->code ?: 'COMP'.$companyId)));
        $companyCode = $companyCode !== '' ? $companyCode : 'COMP'.$companyId;

        $sequence = Asset::query()
            ->where('company_id', $companyId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;

        return sprintf('AST-%s-%s-%04d', $companyCode, now()->format('Ym'), $sequence);
    }

    public function createAsset(int $companyId, array $payload, int $performedBy): Asset
    {
        return DB::transaction(function () use ($companyId, $payload, $performedBy): Asset {
            $asset = Asset::query()->create([
                'company_id' => $companyId,
                'asset_category_id' => (int) $payload['asset_category_id'],
                'asset_code' => $this->generateAssetCode($companyId),
                'name' => trim((string) $payload['name']),
                'brand' => $payload['brand'] ?? null,
                'model' => $payload['model'] ?? null,
                'serial_number' => $payload['serial_number'] ?? null,
                'purchase_date' => $payload['purchase_date'],
                'purchase_price' => $payload['purchase_price'],
                'condition' => $payload['condition'] ?? 'good',
                'status' => $payload['status'] ?? 'available',
                'location' => $payload['location'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'warranty_start_date' => $payload['warranty_start_date'] ?? null,
                'warranty_end_date' => $payload['warranty_end_date'] ?? null,
            ]);

            $this->logAssetAction($asset, 'created', $performedBy, 'Asset created.', null);

            return $asset->load('category');
        });
    }

    public function updateAsset(Asset $asset, array $payload, int $performedBy): Asset
    {
        return DB::transaction(function () use ($asset, $payload, $performedBy): Asset {
            $asset->fill(array_filter([
                'asset_category_id' => $payload['asset_category_id'] ?? null,
                'name' => isset($payload['name']) ? trim((string) $payload['name']) : null,
                'brand' => $payload['brand'] ?? null,
                'model' => $payload['model'] ?? null,
                'serial_number' => array_key_exists('serial_number', $payload) ? $payload['serial_number'] : null,
                'purchase_date' => $payload['purchase_date'] ?? null,
                'purchase_price' => $payload['purchase_price'] ?? null,
                'condition' => $payload['condition'] ?? null,
                'status' => $payload['status'] ?? null,
                'location' => array_key_exists('location', $payload) ? $payload['location'] : null,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : null,
                'warranty_start_date' => $payload['warranty_start_date'] ?? null,
                'warranty_end_date' => $payload['warranty_end_date'] ?? null,
            ], static fn ($value) => $value !== null));

            $asset->save();

            $this->logAssetAction($asset, 'updated', $performedBy, 'Asset updated.', null);

            return $asset->fresh(['category', 'currentAssignment.employeeProfile.user']);
        });
    }

    public function assignAsset(Asset $asset, EmployeeProfile $employeeProfile, array $payload, int $performedBy): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $employeeProfile, $payload, $performedBy): AssetAssignment {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($asset->status !== 'available') {
                throw new RuntimeException('Asset is not available for assignment.');
            }

            if ((int) $employeeProfile->company_id !== (int) $asset->company_id) {
                throw new RuntimeException('Employee does not belong to this company.');
            }

            $existingActive = AssetAssignment::query()
                ->where('company_id', $asset->company_id)
                ->where('asset_id', $asset->id)
                ->where('active_token', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                throw new RuntimeException('Asset already has an active assignment.');
            }

            $assignment = AssetAssignment::query()->create([
                'company_id' => $asset->company_id,
                'asset_id' => $asset->id,
                'employee_id' => $employeeProfile->id,
                'assigned_date' => $payload['assigned_date'] ?? now(),
                'returned_date' => null,
                'condition_at_assign' => $payload['condition_at_assign'] ?? $asset->condition,
                'condition_at_return' => null,
                'active_token' => 'active',
                'notes' => $payload['notes'] ?? null,
            ]);

            $asset->update(['status' => 'assigned']);

            $this->logAssetAction($asset, 'assigned', $performedBy, sprintf(
                'Assigned to employee #%d.',
                $employeeProfile->id
            ), (string) $assignment->id);

            return $assignment->load(['employeeProfile.user', 'asset']);
        });
    }

    public function returnAsset(Asset $asset, array $payload, int $performedBy): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $payload, $performedBy): AssetAssignment {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $assignment = AssetAssignment::query()
                ->where('company_id', $asset->company_id)
                ->where('asset_id', $asset->id)
                ->where('active_token', 'active')
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                throw new RuntimeException('Asset is not currently assigned.');
            }

            $assignment->update([
                'returned_date' => $payload['returned_date'] ?? now(),
                'condition_at_return' => $payload['condition_at_return'] ?? $asset->condition,
                'active_token' => null,
                'notes' => $payload['notes'] ?? $assignment->notes,
            ]);

            $asset->update([
                'status' => 'available',
                'condition' => $payload['condition_at_return'] ?? $asset->condition,
            ]);

            $this->logAssetAction($asset, 'returned', $performedBy, 'Asset returned.', (string) $assignment->id);

            return $assignment->load(['employeeProfile.user', 'asset']);
        });
    }

    public function markMaintenance(Asset $asset, int $performedBy, string $description, ?string $referenceId = null): Asset
    {
        $asset->update(['status' => 'maintenance']);
        $this->logAssetAction($asset, 'maintenance', $performedBy, $description, $referenceId);

        return $asset->fresh();
    }

    public function retireAsset(Asset $asset, int $performedBy, string $description = 'Asset retired.'): Asset
    {
        return DB::transaction(function () use ($asset, $performedBy, $description): Asset {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $asset->update(['status' => 'retired']);
            $asset->delete();

            $this->logAssetAction($asset, 'retired', $performedBy, $description, null);

            return $asset->fresh();
        });
    }

    public function reportIssue(Asset $asset, User $reporter, array $payload): Ticket
    {
        return DB::transaction(function () use ($asset, $reporter, $payload): Ticket {
            $issueType = (string) ($payload['issue_type'] ?? 'damaged');
            $priority = (string) ($payload['priority'] ?? 'high');
            $description = trim((string) ($payload['description'] ?? ''));

            $ticket = Ticket::query()->create([
                'user_id' => $reporter->id,
                'code' => 'AST-'.Str::upper(Str::random(6)).'-'.str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT),
                'subject' => sprintf('Asset %s reported %s', $asset->asset_code, $issueType),
                'description' => trim($description !== '' ? $description : sprintf('Asset issue reported for %s (%s).', $asset->name, $asset->asset_code)),
                'category' => 'asset_issue',
                'priority' => $priority,
                'status' => 'open',
            ]);

            if ($issueType === 'lost') {
                $asset->update(['condition' => 'lost', 'status' => 'retired']);
            } else {
                $asset->update(['condition' => 'damaged', 'status' => 'maintenance']);
            }

            $this->logAssetAction($asset, 'issue_reported', $reporter->id, sprintf(
                'Issue reported as %s. Ticket #%d created.',
                $issueType,
                $ticket->id
            ), (string) $ticket->id);

            return $ticket;
        });
    }

    public function uploadAttachment(Asset $asset, UploadedFile $file, int $performedBy): AssetAttachment
    {
        $storedPath = $file->storePublicly(sprintf('assets/%s', $asset->asset_code), 'public');

        return AssetAttachment::query()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'file_path' => $storedPath,
            'file_type' => (string) $file->getMimeType(),
            'disk' => 'public',
            'original_name' => $file->getClientOriginalName(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $performedBy,
        ]);
    }

    public function logAssetAction(Asset $asset, string $action, int $performedBy, string $description, ?string $referenceId): AssetLog
    {
        return AssetLog::query()->create([
            'company_id' => $asset->company_id,
            'asset_id' => $asset->id,
            'action' => $action,
            'reference_id' => $referenceId,
            'description' => $description,
            'performed_by' => $performedBy,
        ]);
    }
}