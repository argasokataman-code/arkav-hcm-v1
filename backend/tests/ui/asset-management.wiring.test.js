import { describe, expect, it } from 'vitest';

import '../../../frontend/resources/js/asset-management-data.js';

describe('asset management wiring', () => {
  it('rejects asset payload when warranty dates break backend ordering rules', () => {
    expect(window.AssetManagementRules.validateAssetPayload({
      asset_category_id: 10,
      name: 'Laptop Office',
      purchase_date: '2026-04-10',
      warranty_start_date: '2026-04-01',
      warranty_end_date: '2026-04-09',
    })).toContain('Warranty start date tidak boleh lebih awal dari purchase date.');
  });

  it('rejects return payload when returned date is earlier than assigned date', () => {
    expect(window.AssetManagementRules.validateReturnPayload(
      { returned_date: '2026-04-01' },
      { assignedDate: '2026-04-10T00:00:00+00:00' },
    )).toContain('Returned date tidak boleh lebih awal dari assigned date.');
  });

  it('maps backend asset flow errors to user friendly messages', () => {
    expect(window.AssetManagementRules.formatAssetApiError({
      error: { code: 'ASSET_NOT_AVAILABLE', message: 'raw' },
    }, 422)).toBe('Asset tidak tersedia untuk assignment.');
  });

  it('renders manage asset actions with issue and attachment controls', () => {
    const markup = window.AssetManagementRules.buildAssetActionMarkup({ id: 11, status: 'assigned' });
    expect(markup).toContain('data-hcm-asset-return="11"');
    expect(markup).toContain('data-hcm-asset-issue="11"');
    expect(markup).toContain('data-hcm-asset-attach="11"');
  });
});
