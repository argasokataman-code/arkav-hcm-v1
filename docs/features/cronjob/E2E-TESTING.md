# E2E Testing - Cronjob

## Preconditions
- Backend running (`http://127.0.0.1:8007`).
- Login as HCM Admin.

## Scenarios
1. Open `/cronjob` as HCM Admin.
2. Toggle one job off, change time/timezone on another, then click **Save Configuration**.
3. Refresh page and verify values persist.
4. Confirm disabled row stays unchecked after refresh.
5. Login as non-admin and open `/cronjob`.
6. Verify access is blocked (redirect to `lock-screen`).

## Evidence
- Capture pass/fail per scenario with date/time and tested account.
