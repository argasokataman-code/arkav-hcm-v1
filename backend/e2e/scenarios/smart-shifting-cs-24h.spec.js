import { execSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

import { loginViaUi, logoutIfNeeded } from '../helpers/auth.js';

function getActivePackageUuid() {
  const raw = execSync("php -r 'require getcwd()." + '"/vendor/autoload.php"' + "; $app=require getcwd()." + '"/bootstrap/app.php"' + "; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $pkg=App\\Models\\Package::query()->where(\"status\",\"active\")->orderByDesc(\"monthly_price\")->first(); echo $pkg?->uuid ?? \"\";'", {
    cwd: `${process.cwd()}`,
    stdio: ['ignore', 'pipe', 'pipe'],
  })
    .toString()
    .trim();

  if (!raw) {
    throw new Error('No active package found. Cannot run public onboarding flow.');
  }

  return raw;
}

function buildTenantHeaders(ctx) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (ctx?.token) {
    headers.Authorization = `Bearer ${ctx.token}`;
  }
  if (ctx?.companyCode) {
    headers['X-Company-Code'] = String(ctx.companyCode);
  }
  if (ctx?.companyId) {
    headers['X-Company-Id'] = String(ctx.companyId);
  }
  if (ctx?.companyUuid) {
    headers['X-Company-UUID'] = String(ctx.companyUuid);
  }

  return headers;
}

function weekDates(weekStart) {
  const dates = [];
  const start = new Date(`${weekStart}T00:00:00`);
  for (let i = 0; i < 7; i += 1) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    dates.push(`${yyyy}-${mm}-${dd}`);
  }
  return dates;
}

function shiftNameFromStart(startTime) {
  if (startTime === '07:00') return 'Morning';
  if (startTime === '15:00') return 'Afternoon';
  if (startTime === '23:00') return 'Night';
  return 'OFF';
}

function assertEmployeeRules(weeklySchedule) {
  const violations = [];

  const shiftWindow = {
    Morning: { start: 7, end: 15 },
    Afternoon: { start: 15, end: 23 },
    Night: { start: 23, end: 7 },
    OFF: null,
  };

  for (const row of weeklySchedule) {
    const employeeId = String(row.employee_id);
    const assignments = Array.isArray(row.assignments) ? row.assignments : [];

    const normalized = assignments
      .map((item) => ({
        date: String(item.date || ''),
        shiftName: item.shift_id === 'OFF' ? 'OFF' : shiftNameFromStart(String(item.start_time || '')),
      }))
      .sort((a, b) => a.date.localeCompare(b.date));

    const workDays = normalized.filter((item) => item.shiftName !== 'OFF').length;
    const daysOff = 7 - workDays;

    if (workDays > 5) {
      violations.push({ employee_id: employeeId, violation_type: 'MAX_WORK_DAYS', description: `working_days=${workDays}` });
    }
    if (daysOff < 2) {
      violations.push({ employee_id: employeeId, violation_type: 'MIN_DAYS_OFF', description: `days_off=${daysOff}` });
    }

    let maxNightStreak = 0;
    let nightStreak = 0;

    for (let i = 0; i < normalized.length; i += 1) {
      const currentShift = normalized[i].shiftName;

      if (currentShift === 'Night') {
        nightStreak += 1;
        maxNightStreak = Math.max(maxNightStreak, nightStreak);
      } else {
        nightStreak = 0;
      }

      if (i < normalized.length - 1) {
        const nextShift = normalized[i + 1].shiftName;

        if (currentShift === 'Night' && nextShift === 'Morning') {
          violations.push({
            employee_id: employeeId,
            violation_type: 'NIGHT_TO_MORNING',
            description: `${normalized[i].date} Night -> ${normalized[i + 1].date} Morning`,
          });
        }

        if (currentShift !== 'OFF' && nextShift !== 'OFF') {
          const currentWindow = shiftWindow[currentShift];
          const nextWindow = shiftWindow[nextShift];

          const endAbs = currentWindow.end > currentWindow.start ? currentWindow.end : currentWindow.end + 24;
          const startAbs = nextWindow.start + 24;
          const restHours = startAbs - endAbs;

          if (restHours < 12) {
            violations.push({
              employee_id: employeeId,
              violation_type: 'MIN_REST_HOURS',
              description: `${normalized[i].date} ${currentShift} -> ${normalized[i + 1].date} ${nextShift}, rest=${restHours}h`,
            });
          }
        }
      }
    }

    if (maxNightStreak > 3) {
      violations.push({ employee_id: employeeId, violation_type: 'MAX_CONSECUTIVE_NIGHTS', description: `max_streak=${maxNightStreak}` });
    }
  }

  return violations;
}

function assertCoverage(weeklySchedule, expectedCoverage) {
  const coverageByDate = new Map();

  for (const row of weeklySchedule) {
    const assignments = Array.isArray(row.assignments) ? row.assignments : [];

    for (const assignment of assignments) {
      const date = String(assignment.date || '');
      if (!date || assignment.shift_id === 'OFF') {
        continue;
      }

      const shiftName = shiftNameFromStart(String(assignment.start_time || ''));
      if (!['Morning', 'Afternoon', 'Night'].includes(shiftName)) {
        continue;
      }

      if (!coverageByDate.has(date)) {
        coverageByDate.set(date, { Morning: 0, Afternoon: 0, Night: 0 });
      }

      coverageByDate.get(date)[shiftName] += 1;
    }
  }

  const violations = [];
  for (const [date, coverage] of coverageByDate.entries()) {
    for (const shiftName of ['Morning', 'Afternoon', 'Night']) {
      if (coverage[shiftName] !== expectedCoverage[shiftName]) {
        violations.push({
          employee_id: `DAY_${date}`,
          violation_type: 'COVERAGE',
          description: `${shiftName} expected=${expectedCoverage[shiftName]} actual=${coverage[shiftName]}`,
        });
      }
    }
  }

  return violations;
}

test.describe.serial('Smart shifting scenario - full functional flow (no seed)', () => {
  test.afterEach(async ({ page }) => {
    await logoutIfNeeded(page);
  });

  test('register company -> create 100 employees -> generate smart shifting for 30 CS employees', async ({ page }) => {
    const runId = Date.now().toString(36);
    const packageUuid = getActivePackageUuid();
    const ownerEmail = `pw.cs24.owner.${runId}@example.com`;
    const ownerPassword = 'StrongPass1';
    const companyName = `PW CS24 ${runId}`;

    console.log('\n[STEP 1] Public onboarding: register new company + owner');
    const onboardingPayload = {
      package_uuid: packageUuid,
      billing_cycle: 'monthly',
      start_mode: 'trial',
      turnstile_token: 'e2e-headed-token',
      company: {
        name: companyName,
        legal_name: `${companyName} Ltd`,
        timezone: 'Asia/Jakarta',
        currency: 'IDR',
        country_code: 'ID',
        address: 'Jl. Sudirman Kav. 52-53',
        city: 'Jakarta Selatan',
      },
      owner: {
        name: 'Budi Santoso',
        email: ownerEmail,
        password: ownerPassword,
        confirmPassword: ownerPassword,
      },
    };

    const onboardingResponse = await page.request.post('/v1/public/onboarding', { data: onboardingPayload });
    const onboardingBody = await onboardingResponse.json();
    expect(onboardingResponse.ok(), JSON.stringify(onboardingBody, null, 2)).toBeTruthy();
    expect(onboardingBody.success).toBe(true);

    const companyCode = String(onboardingBody?.data?.company?.code || '');
    expect(companyCode).not.toBe('');

    console.log(`[INFO] Company registered: ${companyCode}`);

    console.log('[STEP 2] Login owner via UI (company mode)');
    await loginViaUi(page, {
      email: ownerEmail,
      password: ownerPassword,
    }, {
      companyMode: true,
      companyCode,
      expectedUrlRegex: /\/(index|dashboard|employee-dashboard|subscription)(\?.*)?$/,
    });

    const tenantCtx = await page.evaluate(() => {
      const token = window.localStorage.getItem('arcav_access_token');
      const tenantRaw = window.localStorage.getItem('arcav_active_tenant');
      let tenant = {};
      try {
        tenant = tenantRaw ? JSON.parse(tenantRaw) : {};
      } catch (_err) {
        tenant = {};
      }
      return {
        token,
        companyCode: tenant.companyCode,
        companyId: tenant.companyId,
        companyUuid: tenant.companyUuid,
      };
    });

    const tenantHeaders = buildTenantHeaders(tenantCtx);

    console.log('[STEP 3] Prepare org master data (department + designation)');
    const departmentResponse = await page.request.post('/v1/hcm/departments', {
      headers: tenantHeaders,
      data: {
        name: `Customer Service ${runId}`,
        code: `CS_${runId}`.slice(0, 40),
        isActive: true,
      },
    });
    const departmentBody = await departmentResponse.json();
    expect(departmentResponse.ok(), JSON.stringify(departmentBody, null, 2)).toBeTruthy();
    const departmentId = Number(departmentBody?.data?.id);
    expect(Number.isFinite(departmentId)).toBe(true);

    const designationResponse = await page.request.post('/v1/hcm/designations', {
      headers: tenantHeaders,
      data: {
        name: `Customer Service Agent ${runId}`,
        code: `CSA_${runId}`.slice(0, 40),
        departmentId,
        isActive: true,
      },
    });
    const designationBody = await designationResponse.json();
    expect(designationResponse.ok(), JSON.stringify(designationBody, null, 2)).toBeTruthy();
    const designationId = Number(designationBody?.data?.id);
    expect(Number.isFinite(designationId)).toBe(true);

    console.log('[STEP 4] Create standard CS 24h shift templates (Morning/Afternoon/Night)');
    const shiftTemplates = [
      { key: 'morning', name: 'Morning Shift', code: `pw_m_${runId}`, startTime: '07:00', endTime: '15:00', sortOrder: 10 },
      { key: 'afternoon', name: 'Afternoon Shift', code: `pw_a_${runId}`, startTime: '15:00', endTime: '23:00', sortOrder: 20 },
      { key: 'night', name: 'Night Shift', code: `pw_n_${runId}`, startTime: '23:00', endTime: '07:00', sortOrder: 30 },
    ];

    const createdShiftIds = {};
    for (const shift of shiftTemplates) {
      const shiftResponse = await page.request.post('/v1/hcm/shifts', {
        headers: tenantHeaders,
        data: {
          name: shift.name,
          code: shift.code,
          startTime: shift.startTime,
          endTime: shift.endTime,
          isActive: true,
          sortOrder: shift.sortOrder,
        },
      });
      const shiftBody = await shiftResponse.json();
      expect(shiftResponse.ok(), JSON.stringify(shiftBody, null, 2)).toBeTruthy();
      createdShiftIds[shift.key] = String(shiftBody?.data?.id);
    }

    console.log('[STEP 5] Resolve wilayah IDs for employee creation payload');
    const provincesResponse = await page.request.get('/v1/hcm/wilayah/provinces', { headers: tenantHeaders });
    const provincesBody = await provincesResponse.json();
    expect(provincesResponse.ok(), JSON.stringify(provincesBody, null, 2)).toBeTruthy();
    const provinceId = Number(provincesBody?.data?.[0]?.id);
    expect(Number.isFinite(provinceId)).toBe(true);

    const regenciesResponse = await page.request.get(`/v1/hcm/wilayah/regencies?provinceId=${provinceId}`, { headers: tenantHeaders });
    const regenciesBody = await regenciesResponse.json();
    expect(regenciesResponse.ok(), JSON.stringify(regenciesBody, null, 2)).toBeTruthy();
    const regencyId = Number(regenciesBody?.data?.[0]?.id);
    expect(Number.isFinite(regencyId)).toBe(true);

    const districtsResponse = await page.request.get(`/v1/hcm/wilayah/districts?regencyId=${regencyId}`, { headers: tenantHeaders });
    const districtsBody = await districtsResponse.json();
    expect(districtsResponse.ok(), JSON.stringify(districtsBody, null, 2)).toBeTruthy();
    const districtId = Number(districtsBody?.data?.[0]?.id);
    expect(Number.isFinite(districtId)).toBe(true);

    const villagesResponse = await page.request.get(`/v1/hcm/wilayah/villages?districtId=${districtId}`, { headers: tenantHeaders });
    const villagesBody = await villagesResponse.json();
    expect(villagesResponse.ok(), JSON.stringify(villagesBody, null, 2)).toBeTruthy();
    const villageId = Number(villagesBody?.data?.[0]?.id);
    expect(Number.isFinite(villageId)).toBe(true);

    console.log('[STEP 6] Create 100 employees (first 30 tagged as CS persona for smart shifting scope)');
    const csEmployeeIds = [];

    for (let i = 1; i <= 100; i += 1) {
      const serial = String(i).padStart(3, '0');
      const email = `pw.cs24.${runId}.${serial}@example.com`;
      const isCs = i <= 30;

      const payload = {
        name: `PW Employee ${serial}`,
        email,
        password: 'StrongPass1',
        confirmPassword: 'StrongPass1',
        team: isCs ? 'Customer Service' : 'Backoffice',
        departmentId,
        designationId,
        employeeType: 'permanent',
        employmentStatus: 'active',
        phone: `08123${String(1000000 + i).slice(-7)}`,
        nik: `3174011708${String(100000 + i).padStart(6, '0')}`,
        placeOfBirth: 'Jakarta',
        dateOfBirth: '1998-08-17',
        gender: i % 2 === 0 ? 'female' : 'male',
        maritalStatus: 'single',
        religion: 'Islam',
        nationality: 'Indonesia',
        addressDetail: `Jl. Test No.${i}`,
        provinceId,
        regencyId,
        districtId,
        villageId,
        baseSalary: 6500000,
        fixedAllowance: 500000,
        salaryType: 'monthly',
        contractType: 'permanent',
        contractStatus: 'active',
        contractStartDate: '2025-01-01',
        bankName: 'BCA',
        bankAccountNo: `12345${String(100000 + i)}`,
        bankAccountHolderName: `PW Employee ${serial}`,
        emergencyContacts: [
          {
            name: `Emergency ${serial}`,
            relationship: 'Sibling',
            phone: `08124${String(1000000 + i).slice(-7)}`,
          },
        ],
      };

      const createResponse = await page.request.post('/v1/hcm/employees', {
        headers: tenantHeaders,
        data: payload,
      });
      const createBody = await createResponse.json();
      expect(createResponse.ok(), `employee-${serial} failed:\n${JSON.stringify(createBody, null, 2)}`).toBeTruthy();

      const employeeId = Number(createBody?.data?.id);
      expect(Number.isFinite(employeeId)).toBe(true);
      if (isCs) {
        csEmployeeIds.push(employeeId);
      }

      if (i % 10 === 0) {
        console.log(`[INFO] Employee created: ${i}/100`);
      }
    }

    expect(csEmployeeIds.length).toBe(30);

    console.log('[STEP 6.1] Refresh employee list from tenant API and resolve canonical CS IDs');
    const employeeListResponse = await page.request.get('/v1/hcm/employees?perPage=100&page=1', {
      headers: tenantHeaders,
    });
    const employeeListBody = await employeeListResponse.json();
    expect(employeeListResponse.ok(), JSON.stringify(employeeListBody, null, 2)).toBeTruthy();

    const rows = Array.isArray(employeeListBody?.data) ? employeeListBody.data : [];
    const csScopedIds = rows
      .filter((row) => String(row?.team || '').toLowerCase() === 'customer service')
      .map((row) => Number(row?.userId ?? row?.id))
      .filter((id) => Number.isFinite(id))
      .slice(0, 30);

    if (csScopedIds.length === 30) {
      csEmployeeIds.length = 0;
      csEmployeeIds.push(...csScopedIds);
    }

    expect(csEmployeeIds.length).toBe(30);

    console.log('[STEP 7] Open schedule-timing page and ensure smart planner form is visible');
    await page.goto('/schedule-timing', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('[data-smart-planner-form]')).toBeVisible();
    await page.locator('[data-smart-planner-shift-category]').selectOption('shifting_24h');

    const weekStart = new Date();
    const day = weekStart.getDay();
    const delta = day === 0 ? -6 : 1 - day;
    weekStart.setDate(weekStart.getDate() + delta);
    const weekStartIso = `${weekStart.getFullYear()}-${String(weekStart.getMonth() + 1).padStart(2, '0')}-${String(weekStart.getDate()).padStart(2, '0')}`;

    await page.locator('[data-smart-planner-week-start]').fill(weekStartIso);

    console.log('[STEP 8] Trigger smart attendance shifting generation for 30 CS employees');
    const coverageDates = weekDates(weekStartIso);
    const plannerPayload = {
      weekStart: weekStartIso,
      shiftCategory: 'shifting_24h',
      employeeIds: csEmployeeIds,
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
          { shift_id: createdShiftIds.morning, headcount: 8 },
          { shift_id: createdShiftIds.afternoon, headcount: 8 },
          { shift_id: createdShiftIds.night, headcount: 4 },
        ],
      })),
    };

    const plannerResponse = await page.request.post('/v1/hcm/smart-attendance-shifting/generate', {
      headers: tenantHeaders,
      data: plannerPayload,
    });
    const plannerBody = await plannerResponse.json();
    expect(plannerResponse.ok(), JSON.stringify(plannerBody, null, 2)).toBeTruthy();
    expect(plannerBody?.success).toBe(true);

    console.log('[STEP 9] Validate generated weekly schedule against QA assertions');
    const weeklySchedule = plannerBody?.data?.schedule_generation?.weekly_schedule || [];
    expect(Array.isArray(weeklySchedule)).toBe(true);
    expect(weeklySchedule.length).toBe(30);

    const employeeViolations = assertEmployeeRules(weeklySchedule);
    const coverageViolations = assertCoverage(weeklySchedule, { Morning: 8, Afternoon: 8, Night: 4 });
    const apiViolations = Array.isArray(plannerBody?.data?.schedule_generation?.violations)
      ? plannerBody.data.schedule_generation.violations.map((item) => ({
          employee_id: String(item.employee_id ?? 'SYSTEM'),
          violation_type: String(item.code ?? 'SCHEDULE_VIOLATION'),
          description: String(item.message ?? 'Unknown violation'),
        }))
      : [];

    const allViolations = [...employeeViolations, ...coverageViolations, ...apiViolations];

    const totalAssertions = (30 * 5) + (7 * 3);
    const totalFailed = allViolations.length;
    const totalPassed = totalAssertions - totalFailed;

    const fairnessScore = Number(plannerBody?.data?.recommendation?.fairness_score ?? 0);
    const fatigueRiskScore = Number(plannerBody?.data?.recommendation?.fatigue_risk_score ?? 0);
    const coverageStatus = totalFailed === 0 ? 'OK' : 'NOT OK';

    console.log('\n========== QA SUMMARY ==========');
    console.log('total_passed:', totalPassed);
    console.log('total_failed:', totalFailed);
    console.log('fairness_score:', fairnessScore);
    console.log('fatigue_risk_score:', fatigueRiskScore);
    console.log('coverage_status:', coverageStatus);
    console.log('list_of_violations:', JSON.stringify(allViolations, null, 2));
    console.log('===============================\n');

    expect(allViolations, JSON.stringify(allViolations, null, 2)).toHaveLength(0);
  });
});
