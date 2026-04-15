import { chromium } from 'playwright';

async function run() {
  const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:5179';
  const email = process.env.SMOKE_EMAIL || 'qa.hcm@example.com';
  const password = process.env.SMOKE_PASSWORD || 'StrongPass1';

  const routes = [
    '/employees',
    '/employee-details',
    '/departments',
    '/designations',
    '/policy',
    '/ticket-master',
    '/tickets-admin',
    '/holidays',
    '/leaves',
    '/leave-settings',
    '/attendance-admin',
    '/timesheets',
    '/schedule-timing',
    '/shift-master',
    '/overtime-master',
    '/overtime',
    '/performance-indicator',
    '/performance-review',
    '/goal-tracking',
    '/training',
    '/promotion',
    '/resignation',
    '/termination',
    '/employee-salary',
    '/payroll',
    '/payroll-run',
    '/payroll-run-history',
    '/payroll-thr',
    '/payroll-pkwt-compensation',
    '/payslip',
    '/users',
    '/roles-permissions',
  ];

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    baseURL,
    viewport: { width: 1440, height: 960 },
  });

  const routeErrors = new Map();
  let currentRoute = 'init';

  page.on('pageerror', (err) => {
    const errors = routeErrors.get(currentRoute) || [];
    errors.push(String(err.message || err).slice(0, 200));
    routeErrors.set(currentRoute, errors);
  });

  const results = [];

  try {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('#login-email', email);
    await page.fill('#login-password', password);
    await page.click('#login-submit');
    await page.waitForURL(/\/index$/, { timeout: 20000 });

    for (const route of routes) {
      currentRoute = route;
      routeErrors.set(route, []);

      let status = 0;
      let finalUrl = '';
      let ok = true;
      let reason = 'ok';
      let metrics = {
        contentTextLen: 0,
        cards: 0,
        tables: 0,
        forms: 0,
        buttons: 0,
        inputs: 0,
        hcmAttrs: 0,
        sampleText: '',
      };

      try {
        const response = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        status = response ? response.status() : 0;
        finalUrl = page.url();

        metrics = await page.evaluate(() => {
          const candidates = [
            document.querySelector('.page-wrapper'),
            document.querySelector('.content'),
            document.querySelector('main'),
            document.body,
          ].filter(Boolean);

          const picked = candidates
            .map((el) => ({ el, len: (el.innerText || '').replace(/\s+/g, ' ').trim().length }))
            .sort((a, b) => b.len - a.len)[0]?.el || document.body;

          const count = (selector) => picked.querySelectorAll(selector).length;
          const text = (picked.innerText || '').replace(/\s+/g, ' ').trim();

          return {
            contentTextLen: text.length,
            cards: count('.card'),
            tables: count('table'),
            forms: count('form'),
            buttons: count('button, [role="button"], .btn, a.btn'),
            inputs: count('input, select, textarea'),
            hcmAttrs: count('[data-hcm], [data-hcm-page], [data-hcm-table], [data-hcm-form], [data-hcm-employee-salary-body]'),
            sampleText: text.slice(0, 90),
          };
        });

        const jsErrors = routeErrors.get(route) || [];
        const redirectLogin = /\/login($|\?)/.test(finalUrl);
        const hasLaravelError = (await page.content()).includes('Whoops, looks like something went wrong.');
        const hasMeaningfulContent = metrics.contentTextLen >= 120;
        const hasInteractive =
          metrics.tables + metrics.forms + metrics.buttons + metrics.inputs + metrics.cards + metrics.hcmAttrs >= 3;

        if (status >= 400) {
          ok = false;
          reason = `http_${status}`;
        } else if (redirectLogin) {
          ok = false;
          reason = 'redirect_login';
        } else if (hasLaravelError) {
          ok = false;
          reason = 'laravel_error_page';
        } else if (!hasMeaningfulContent || !hasInteractive) {
          ok = false;
          reason = `thin_content(text=${metrics.contentTextLen},interactive=${metrics.tables + metrics.forms + metrics.buttons + metrics.inputs + metrics.cards + metrics.hcmAttrs})`;
        } else if (jsErrors.length > 0) {
          ok = false;
          reason = `js_error(${jsErrors[0]})`;
        }
      } catch (error) {
        ok = false;
        reason = `exception(${String(error.message || error).slice(0, 120)})`;
        finalUrl = page.url();
      }

      const row = {
        route,
        ok,
        reason,
        status,
        finalUrl,
        ...metrics,
        jsErrors: routeErrors.get(route) || [],
      };

      results.push(row);
      console.log(
        `${ok ? 'PASS' : 'FAIL'} ${route} :: ${reason} :: text=${metrics.contentTextLen} cards=${metrics.cards} tables=${metrics.tables} forms=${metrics.forms} buttons=${metrics.buttons} inputs=${metrics.inputs} hcm=${metrics.hcmAttrs}`
      );

      if (metrics.sampleText) {
        console.log(`  SAMPLE: ${metrics.sampleText}`);
      }
    }

    const failed = results.filter((item) => !item.ok);
    console.log('--- FEATURE CONTENT SUMMARY ---');
    console.log(`TOTAL=${results.length}`);
    console.log(`PASSED=${results.length - failed.length}`);
    console.log(`FAILED=${failed.length}`);

    if (failed.length > 0) {
      console.log(`FAILED_DETAILS=${JSON.stringify(failed, null, 2)}`);
      process.exitCode = 2;
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
