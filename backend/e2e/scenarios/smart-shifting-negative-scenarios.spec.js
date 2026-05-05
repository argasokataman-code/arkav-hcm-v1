/**
 * E2E Spec: Smart Attendance — Negative Scenarios
 *
 * Flow:
 * 1. Onboard company → create master shifts (Morning/Afternoon/Night)
 * 2. Create 12 employees: 10 CS actives + 1 on-leave + 1 resigned
 * 3. Inject approved leave request for employee #11
 * 4. Inject approved resignation for employee #12
 * 5. Generate smart attendance → assert employee #12 excluded, #11 has unavailable_dates
 * 6. Simulate swap between employee #1 (Morning) and #2 (Night) → assert feasibility + risks
 * 7. Mark employee #3 absent → find replacement → assert candidate returned
 */

import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { loginViaUi, logoutIfNeeded } from '../helpers/auth.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getActivePackageUuid() {
  const raw = execSync(
    "php -r 'require getcwd()." +
      '"/vendor/autoload.php"' +
      "; $app=require getcwd()." +
      '"/bootstrap/app.php"' +
      "; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $pkg=App\\Models\\Package::query()->where(\"status\",\"active\")->orderByDesc(\"monthly_price\")->first(); echo $pkg?->uuid ?? \"\";'",
    { cwd: process.cwd(), stdio: ['ignore', 'pipe', 'pipe'] }
  )
    .toString()
    .trim();
  if (!raw) throw new Error('No active package found.');
  return raw;
}

function buildHeaders(ctx) {
  const h = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (ctx?.token) h.Authorization = `Bearer ${ctx.token}`;
  if (ctx?.companyCode) h['X-Company-Code'] = String(ctx.companyCode);
  if (ctx?.companyId) h['X-Company-Id'] = String(ctx.companyId);
  if (ctx?.companyUuid) h['X-Company-UUID'] = String(ctx.companyUuid);
  return h;
}

function nextMonday() {
  const d = new Date();
  const day = d.getDay();
  const delta = day === 0 ? 1 : 8 - day;
  d.setDate(d.getDate() + delta);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function addDays(iso, n) {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// ─────────────────────────────────────────────────────────────────────────────

test.describe.serial('Smart Attendance — Negative Scenarios (leave / resign / swap / replacement)', () => {
  test.afterEach(async ({ page }) => {
    await logoutIfNeeded(page);
  });

  test('Full negative-scenario flow: leave exclusion, resign exclusion, swap simulation, replacement finder', async ({ page }) => {
    const runId = Date.now().toString(36);
    const packageUuid = getActivePackageUuid();
    const ownerEmail = `pw.neg.${runId}@example.com`;
    const ownerPassword = 'StrongPass1';

    // ── STEP 1: Onboard company ──────────────────────────────────────────────
    console.log('\n[STEP 1] Onboard company');
    const onboardRes = await page.request.post('/v1/public/onboarding', {
      data: {
        package_uuid: packageUuid,
        billing_cycle: 'monthly',
        start_mode: 'trial',
        turnstile_token: 'e2e-headed-token',
        company: {
          name: `PW Neg ${runId}`,
          legal_name: `PW Neg ${runId} Ltd`,
          timezone: 'Asia/Jakarta',
          currency: 'IDR',
          country_code: 'ID',
          address: 'Jl. Test Neg',
          city: 'Jakarta',
        },
        owner: {
          name: 'Owner Neg',
          email: ownerEmail,
          password: ownerPassword,
          confirmPassword: ownerPassword,
        },
      },
    });
    const onboardBody = await onboardRes.json();
    expect(onboardRes.ok(), JSON.stringify(onboardBody)).toBeTruthy();
    const companyCode = String(onboardBody?.data?.company?.code || '');
    expect(companyCode).not.toBe('');
    console.log(`[INFO] company: ${companyCode}`);

    // ── STEP 2: Login ────────────────────────────────────────────────────────
    console.log('[STEP 2] Login owner');
    await loginViaUi(page, { email: ownerEmail, password: ownerPassword }, {
      companyMode: true,
      companyCode,
      expectedUrlRegex: /\/(index|dashboard|employee-dashboard|subscription)(\?.*)?$/,
    });

    const ctx = await page.evaluate(() => {
      const token = window.localStorage.getItem('arcav_access_token');
      const tenantRaw = window.localStorage.getItem('arcav_active_tenant');
      let tenant = {};
      try { tenant = tenantRaw ? JSON.parse(tenantRaw) : {}; } catch (_) { tenant = {}; }
      return { token, companyCode: tenant.companyCode, companyId: tenant.companyId, companyUuid: tenant.companyUuid };
    });
    const h = buildHeaders(ctx);

    // ── STEP 3: Create master shifts ─────────────────────────────────────────
    console.log('[STEP 3] Create master shifts (Morning / Afternoon / Night)');
    const shiftDefs = [
      { key: 'morning',   name: 'Morning Shift',   code: `neg_m_${runId}`, startTime: '07:00', endTime: '15:00' },
      { key: 'afternoon', name: 'Afternoon Shift',  code: `neg_a_${runId}`, startTime: '15:00', endTime: '23:00' },
      { key: 'night',     name: 'Night Shift',      code: `neg_n_${runId}`, startTime: '23:00', endTime: '07:00' },
    ];
    const shiftIds = {};
    for (const s of shiftDefs) {
      const r = await page.request.post('/v1/hcm/shifts', {
        headers: h,
        data: { name: s.name, code: s.code, startTime: s.startTime, endTime: s.endTime, isActive: true, sortOrder: 10 },
      });
      const b = await r.json();
      expect(r.ok(), `create shift ${s.key}: ${JSON.stringify(b)}`).toBeTruthy();
      shiftIds[s.key] = String(b?.data?.id);
      console.log(`[INFO] shift ${s.key}: id=${shiftIds[s.key]}`);
    }

    // ── STEP 4: Org master data ───────────────────────────────────────────────
    console.log('[STEP 4] Department + designation');
    const deptRes = await page.request.post('/v1/hcm/departments', {
      headers: h,
      data: { name: `CS Neg ${runId}`, code: `CS_NEG_${runId}`.slice(0, 40), isActive: true },
    });
    const deptBody = await deptRes.json();
    expect(deptRes.ok(), JSON.stringify(deptBody)).toBeTruthy();
    const departmentId = Number(deptBody?.data?.id);

    const desigRes = await page.request.post('/v1/hcm/designations', {
      headers: h,
      data: { name: `CS Agent Neg ${runId}`, code: `CSA_NEG_${runId}`.slice(0, 40), departmentId, isActive: true },
    });
    const desigBody = await desigRes.json();
    expect(desigRes.ok(), JSON.stringify(desigBody)).toBeTruthy();
    const designationId = Number(desigBody?.data?.id);

    // ── STEP 5: Wilayah ───────────────────────────────────────────────────────
    const provRes = await page.request.get('/v1/hcm/wilayah/provinces', { headers: h });
    const provBody = await provRes.json();
    const provinceId = Number(provBody?.data?.[0]?.id);

    const regRes = await page.request.get(`/v1/hcm/wilayah/regencies?provinceId=${provinceId}`, { headers: h });
    const regBody = await regRes.json();
    const regencyId = Number(regBody?.data?.[0]?.id);

    const distRes = await page.request.get(`/v1/hcm/wilayah/districts?regencyId=${regencyId}`, { headers: h });
    const distBody = await distRes.json();
    const districtId = Number(distBody?.data?.[0]?.id);

    const vilRes = await page.request.get(`/v1/hcm/wilayah/villages?districtId=${districtId}`, { headers: h });
    const vilBody = await vilRes.json();
    const villageId = Number(vilBody?.data?.[0]?.id);

    const baseEmployee = {
      departmentId, designationId,
      employeeType: 'permanent', employmentStatus: 'active',
      placeOfBirth: 'Jakarta', dateOfBirth: '1998-08-17',
      gender: 'male', maritalStatus: 'single', religion: 'Islam', nationality: 'Indonesia',
      addressDetail: 'Jl. Neg Test', provinceId, regencyId, districtId, villageId,
      baseSalary: 6000000, fixedAllowance: 500000, salaryType: 'monthly',
      contractType: 'permanent', contractStatus: 'active', contractStartDate: '2025-01-01',
      bankName: 'BCA', bankAccountNo: '123456789', bankAccountHolderName: 'Employee',
      emergencyContacts: [{ name: 'Emergency', relationship: 'Sibling', phone: '081234567890' }],
    };

    // ── STEP 6: Create 12 employees ──────────────────────────────────────────
    console.log('[STEP 6] Create 12 employees (10 active + 1 on-leave + 1 resigned)');
    const employeeUserIds = [];
    for (let i = 1; i <= 12; i++) {
      const serial = String(i).padStart(2, '0');
      const r = await page.request.post('/v1/hcm/employees', {
        headers: h,
        data: {
          ...baseEmployee,
          name: `Neg Emp ${serial}`,
          email: `pw.neg.${runId}.emp${serial}@example.com`,
          password: 'StrongPass1',
          confirmPassword: 'StrongPass1',
          phone: `0812${String(10000000 + i).slice(-8)}`,
          nik: `3174011708${String(100000 + i).padStart(6, '0')}`,
          bankAccountNo: `99${String(100000 + i)}`,
          bankAccountHolderName: `Neg Emp ${serial}`,
        },
      });
      const b = await r.json();
      expect(r.ok(), `emp-${serial}: ${JSON.stringify(b)}`).toBeTruthy();
      employeeUserIds.push(Number(b?.data?.userId ?? b?.data?.id));
    }
    expect(employeeUserIds.length).toBe(12);
    console.log('[INFO] Employee userIds:', employeeUserIds);

    const weekStart = nextMonday();
    const weekEnd = addDays(weekStart, 6);
    const leaveDay = addDays(weekStart, 2); // Wednesday

    // ── STEP 7: Inject approved leave for employee #11 ───────────────────────
    console.log(`[STEP 7] Inject approved leave for employee #11 (userId=${employeeUserIds[10]}) on ${leaveDay}`);
    const leaveRes = await page.request.post('/v1/hcm/leaves/requests', {
      headers: h,
      data: {
        userId: employeeUserIds[10],
        leaveTypeId: null,
        dateFrom: leaveDay,
        dateTo: leaveDay,
        reason: 'E2E negative test leave',
        status: 'approved',
      },
    });
    const leaveBody = await leaveRes.json();
    // If endpoint requires manager approval flow, we do it via direct DB injection
    if (!leaveRes.ok()) {
      console.log(`[WARN] Leave request POST not OK (${leaveRes.status()}): ${JSON.stringify(leaveBody)} — injecting via artisan`);
      execSync(
        `php artisan tinker --no-interaction --execute="DB::table('leave_requests')->insert(['user_id'=>${employeeUserIds[10]},'company_id'=>${ctx.companyId},'date_from'=>'${leaveDay}','date_to'=>'${leaveDay}','reason'=>'e2e neg test','status'=>'approved','created_at'=>now(),'updated_at'=>now()]);"`,
        { cwd: process.cwd(), stdio: 'inherit' }
      );
    } else {
      // Approve it if created as pending
      const leaveId = leaveBody?.data?.id;
      if (leaveId && leaveBody?.data?.status !== 'approved') {
        const approveRes = await page.request.put(`/v1/hcm/leaves/requests/${leaveId}/status`, {
          headers: h,
          data: { status: 'approved' },
        });
        console.log(`[INFO] Leave approve status: ${approveRes.status()}`);
      }
    }

    // ── STEP 8: Inject approved resignation for employee #12 ─────────────────
    console.log(`[STEP 8] Inject approved resignation for employee #12 (userId=${employeeUserIds[11]})`);
    const resignRes = await page.request.post('/v1/hcm/resignations', {
      headers: h,
      data: {
        userId: employeeUserIds[11],
        resignationDate: addDays(weekStart, 3),
        reason: 'E2E negative test resign',
        status: 'approved',
      },
    });
    const resignBody = await resignRes.json();
    if (!resignRes.ok()) {
      console.log(`[WARN] Resignation POST not OK (${resignRes.status()}): ${JSON.stringify(resignBody)} — injecting via artisan`);
      execSync(
        `php artisan tinker --no-interaction --execute="DB::table('hcm_resignations')->insert(['user_id'=>${employeeUserIds[11]},'company_id'=>${ctx.companyId},'resignation_date'=>'${addDays(weekStart, 3)}','reason'=>'e2e neg test','status'=>'approved','created_at'=>now(),'updated_at'=>now()]);"`,
        { cwd: process.cwd(), stdio: 'inherit' }
      );
    }

    // ── STEP 9: Generate smart attendance ────────────────────────────────────
    console.log(`[STEP 9] Generate smart attendance — weekStart=${weekStart} with all 12 employee IDs`);
    const coverageDates = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));
    const plannerPayload = {
      weekStart,
      shiftCategory: 'shifting_24h',
      employeeIds: employeeUserIds,
      rules: {
        max_work_days_per_week: 5,
        min_days_off_per_week: 2,
        min_rest_hours_between_shifts: 12,
        max_consecutive_night_shifts: 3,
        illegal_transition_rules: ['night_to_morning'],
      },
      coverageRequirements: coverageDates.map((date) => ({
        date,
        required: [
          { shift_id: shiftIds.morning,   headcount: 3 },
          { shift_id: shiftIds.afternoon, headcount: 3 },
          { shift_id: shiftIds.night,     headcount: 2 },
        ],
      })),
    };

    const genRes = await page.request.post('/v1/hcm/smart-attendance-shifting/generate', {
      headers: h,
      data: plannerPayload,
    });
    const genBody = await genRes.json();
    expect(genRes.ok(), JSON.stringify(genBody, null, 2)).toBeTruthy();
    expect(genBody?.success).toBe(true);

    const weeklySchedule = genBody?.data?.schedule_generation?.weekly_schedule ?? [];
    console.log(`[INFO] Schedule rows returned: ${weeklySchedule.length}`);

    // ── Assert: resigned employee (#12) should NOT appear in schedule ────────
    const resignedUserId = employeeUserIds[11];
    const resignedInSchedule = weeklySchedule.some(
      (row) => Number(row.employee_id) === resignedUserId
    );
    console.log(`[ASSERT] Resigned employee (${resignedUserId}) in schedule: ${resignedInSchedule}`);
    expect(resignedInSchedule, `Resigned employee userId=${resignedUserId} should be excluded from schedule`).toBe(false);

    // ── Assert: on-leave employee (#11) should have unavailable_dates or no active shift on leaveDay ──
    const onLeaveUserId = employeeUserIds[10];
    const leaveRow = weeklySchedule.find((row) => Number(row.employee_id) === onLeaveUserId);
    if (leaveRow) {
      const assignmentOnLeaveDay = (leaveRow.assignments ?? []).find((a) => String(a.date) === leaveDay);
      const hasUnavailableDates = Array.isArray(leaveRow.unavailable_dates) && leaveRow.unavailable_dates.includes(leaveDay);
      const shiftOnLeaveDay = assignmentOnLeaveDay?.shift_id;
      console.log(`[ASSERT] On-leave employee (${onLeaveUserId}) on ${leaveDay}: shift=${shiftOnLeaveDay}, unavailable_dates_includes=${hasUnavailableDates}`);
      // Either unavailable_dates marks that day OR employee is scheduled OFF
      expect(
        hasUnavailableDates || shiftOnLeaveDay === 'OFF' || shiftOnLeaveDay == null,
        `On-leave employee should have ${leaveDay} as OFF or in unavailable_dates, got shift_id=${shiftOnLeaveDay}`
      ).toBe(true);
    } else {
      console.log(`[INFO] On-leave employee (${onLeaveUserId}) not in schedule rows (may have been excluded entirely — also valid).`);
    }

    // ── STEP 10: Simulate swap between employee #1 (Morning) and employee #2 ─
    console.log('[STEP 10] Simulate swap — employee #1 swaps date with employee #2');
    const swapPayload = {
      userAId: employeeUserIds[0],
      userBId: employeeUserIds[1],
      swapDateA: weekStart,
      swapDateB: addDays(weekStart, 1),
    };

    const swapRes = await page.request.post('/v1/hcm/smart-attendance-shifting/simulate-swap', {
      headers: h,
      data: swapPayload,
    });
    const swapBody = await swapRes.json();
    expect(swapRes.ok(), JSON.stringify(swapBody, null, 2)).toBeTruthy();
    expect(swapBody?.success).toBe(true);

    const swapData = swapBody?.data ?? {};
    console.log('[ASSERT] Swap result:');
    console.log('  swap_summary:', swapData.swap_summary);
    console.log('  overall_risk_level:', swapData.overall_risk_level);
    console.log('  swappable:', swapData.swappable);
    console.log('  employee_a:', JSON.stringify(swapData.employee_a));
    console.log('  employee_b:', JSON.stringify(swapData.employee_b));
    console.log('  warnings:', JSON.stringify(swapData.warnings));
    console.log('  advice:', swapData.advice);

    // Swap response must have the required shape
    expect(typeof swapData.swappable).toBe('boolean');
    expect(['swap_summary', 'overall_risk_level', 'advice'].every((k) => k in swapData)).toBe(true);
    if (swapData.swappable) {
      expect(swapData.employee_a).toBeTruthy();
      expect(swapData.employee_b).toBeTruthy();
    }

    // ── STEP 11: Find replacement for absent employee #3 on weekStart ────────
    console.log('[STEP 11] Find replacement for absent employee #3');

    // Determine which shift employee #3 was assigned on weekStart
    const emp3Row = weeklySchedule.find((row) => Number(row.employee_id) === employeeUserIds[2]);
    const emp3Assignment = (emp3Row?.assignments ?? []).find((a) => String(a.date) === weekStart);
    const emp3ShiftId = emp3Assignment?.shift_id !== 'OFF' ? emp3Assignment?.shift_id : null;
    // Fallback to morning shift if not found
    const targetShiftId = emp3ShiftId ?? shiftIds.morning;

    const replacementPayload = {
      absentUserId: employeeUserIds[2],
      absentDates: [weekStart],
      shiftId: targetShiftId,
    };

    const replacementRes = await page.request.post('/v1/hcm/smart-attendance-shifting/find-replacement', {
      headers: h,
      data: replacementPayload,
    });
    const replacementBody = await replacementRes.json();
    expect(replacementRes.ok(), JSON.stringify(replacementBody, null, 2)).toBeTruthy();
    expect(replacementBody?.success).toBe(true);

    const replacementData = replacementBody?.data ?? {};
    console.log('[ASSERT] Replacement result:');
    console.log('  message:', replacementData.message);
    console.log('  candidates count:', (replacementData.candidates ?? []).length);
    console.log('  candidates:', JSON.stringify(replacementData.candidates?.slice(0, 3)));

    // With 10+ active employees available, there should be candidates
    expect(['message', 'candidates'].every((k) => k in replacementData)).toBe(true);
    expect(Array.isArray(replacementData.candidates)).toBe(true);
    // At least 1 candidate should be found (we have 9+ other active employees)
    expect(
      replacementData.candidates.length,
      `Expected at least 1 replacement candidate, got ${replacementData.candidates.length}. Message: ${replacementData.message}`
    ).toBeGreaterThanOrEqual(1);

    // Absent employee must NOT be in candidates
    const absentInCandidates = replacementData.candidates.some(
      (c) => Number(c.employee_id ?? c.userId) === employeeUserIds[2]
    );
    expect(absentInCandidates, 'Absent employee must not appear in replacement candidates').toBe(false);

    // ── STEP 12: Summary ─────────────────────────────────────────────────────
    console.log('\n========== NEGATIVE SCENARIO SUMMARY ==========');
    console.log('master_shifts_created:', Object.keys(shiftIds).join(', '));
    console.log('employees_total:', employeeUserIds.length);
    console.log('resigned_excluded_from_schedule:', !resignedInSchedule ? 'PASS ✅' : 'FAIL ❌');
    console.log('on_leave_marked_correctly:', 'PASS ✅ (or not in schedule)');
    console.log('swap_simulation_response_shape:', 'PASS ✅');
    console.log('swap_swappable:', swapData.swappable ? 'swappable' : 'not-swappable (risk flagged)');
    console.log('replacement_candidates_found:', replacementData.candidates.length);
    console.log('replacement_absent_not_in_candidates:', !absentInCandidates ? 'PASS ✅' : 'FAIL ❌');
    console.log('================================================\n');
  });
});
