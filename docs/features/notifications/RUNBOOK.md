# Notification System Runbook & Troubleshooting Guide

## Overview

This runbook provides operational guidance for monitoring, diagnosing, and resolving notification delivery issues in the Arcav HCM platform. It covers the observability dashboard, error categories, manual remediation procedures, and forensic analysis.

**Target Audience**: Global HCM admins, platform operations team, support engineers.

---

## Quick Reference: Observability Dashboard

**URL**: `/notification-observability` (Global Admin only)

### Dashboard Components

| Component | Purpose | Filters |
|-----------|---------|---------|
| **Totals Card** | Delivery counts by status (all/sent/failed/dropped) | Hours window (1-720); Channel |
| **Status Breakdown** | Pie/bar chart by channel | Same as above |
| **Top Failed Events** | Most common failure event keys (clickable) | Same as above |
| **Export Button** | CSV download of delivery records | Status (sent/failed/dropped); Hours; Channel; Event Key |

### Key Metrics

- **All**: Total deliveries in window (all statuses)
- **Sent**: Successfully delivered
- **Failed**: Attempted but failed (eligible for manual retry)
- **Dropped**: Abandoned (exhausted retries or system error)

---

## Error Categories & Resolution

The notification system categorizes delivery errors into standardized types. Use this guide to diagnose and resolve common issues.

### 1. SMTP Errors (Email Channel)

#### Category: `smtp_timeout`
**Meaning**: Connection to mail server timed out during handshake or data transfer.

**Causes**:
- Mail server unreachable or slow
- Network connectivity issues
- Firewall blocking outbound SMTP traffic
- Mail server under heavy load

**Resolution Steps**:
1. Check mail server DNS: `nslookup <mail_server_host>`
2. Verify connectivity: `telnet <host> <port>` (e.g., `telnet smtp.gmail.com 587`)
3. Review application `config/mail.php` for stale server settings
4. Contact mail provider support if server status is unknown
5. Consider increasing timeout via app config or mail driver settings
6. Manual retry via observability dashboard after resolution

---

#### Category: `smtp_auth_failed`
**Meaning**: SMTP authentication failed (invalid credentials or expired token).

**Causes**:
- Incorrect username/password in config
- API token expired (e.g., SendGrid, AWS SES)
- Credentials rotated but app not updated
- Rate limit on auth attempts

**Resolution Steps**:
1. Verify app credentials in `.env`: `MAIL_USERNAME`, `MAIL_PASSWORD`
2. If using token-based auth (SendGrid, SES), check token expiration
3. Re-generate credentials if needed from mail provider dashboard
4. Update `.env` with new credentials, re-cache if running PHP artisan config:cache
5. Clear any failed auth rate limits (wait 15 min or reset auth service)
6. Test single delivery via Tinker: `Mail::raw('test', fn($m) => $m->to('admin@test.com')->subject('test'))->send()`
7. Manual retry in observability dashboard

---

#### Category: `smtp_tls_error`
**Meaning**: TLS/SSL negotiation failed during secure connection setup.

**Causes**:
- SSL certificate expired on mail server
- Cipher mismatch between client and server
- TLS version mismatch (app using old TLS)
- Firewall intercepting SSL traffic

**Resolution Steps**:
1. Check mail server certificate: `openssl s_client -connect <host>:<port> -starttls smtp`
2. Verify TLS version in `config/mail.php` (should support TLS 1.2 minimum)
3. If intermediate certificate needed, contact mail provider
4. Consider falling back to unencrypted connection if safe for internal network
5. Update PHP OpenSSL if outdated
6. Manual retry after TLS configuration adjusted

---

#### Category: `smtp_error` (Generic)
**Meaning**: SMTP protocol error not classified above (e.g., "454 Too many connections").

**Causes**:
- Mail server connection limit exceeded
- Session limit per IP address
- Malformed SMTP command
- Server restarting/maintenance

**Resolution Steps**:
1. Check error detail in observability dashboard delivery drilldown
2. Review mail server logs (if accessible) or contact provider for status
3. Implement rate limiting on notification producer (backoff policy)
4. Wait 5-15 min and retry if server was restarting
5. Consider connection pooling or queue batching

---

### 2. Recipient & Validation Errors

#### Category: `invalid_recipient`
**Meaning**: Email address invalid or malformed.

**Causes**:
- Typo in recipient email stored in database
- Null/empty recipient field not caught by validation
- Special characters breaking email parser
- Recipient field from buggy data migration

**Resolution Steps**:
1. Inspect delivery record in CSV export or dashboard drilldown
2. Check source of recipient (User model, Member record, etc.)
3. Query database for malformed entries: `SELECT * FROM notification_deliveries WHERE recipient LIKE '%@%.%' IS FALSE`
4. Correct recipient data at source (e.g., fix User email field)
5. This error does NOT eligible for manual retry (fix data first)
6. Optionally manually fix notification_deliveries record if auditing required

---

#### Category: `bounce_error`
**Meaning**: Email server accepted message but later bounced it (often hard bounce).

**Causes**:
- Recipient email no longer exists or disabled
- ISP blacklisted sender domain
- Recipient mailbox full (soft bounce, may succeed on retry)
- Spam filter aggressive rejection

**Resolution Steps**:
1. Identify bounce type from ISP report (hard/soft)
2. For hard bounce: email address is invalid, remove/update recipient
3. For soft bounce: retry after mailbox maintenance
4. Review sender reputation: check SPF/DKIM/DMARC records for your mail domain
5. If domain blacklisted, contact removal service
6. Manual retry for soft bounces in observability dashboard

---

### 3. Rate Limiting & Abuse Guard

#### Category: `rate_limit`
**Meaning**: Mail server or API rejected request due to rate limit.

**Causes**:
- Tenant exceeded send quota for hourly/daily window
- Shared IP rate limit exhausted (SendGrid, AWS SES)
- API plan throughput exceeded
- Bot/spam pattern triggered

**Resolution Steps**:
1. Check mail provider rate limit documentation for reset window
2. Review sending patterns: are notifications batched unnecessarily?
3. Consider staggering sends via queue delay if available
4. For SaaS mail providers, upgrade plan if rate limit insufficient
5. Wait for reset window (usually 1 hour) and retry
6. If abuse pattern suspected, audit event producers for loops

---

### 4. Network & Infrastructure Errors

#### Category: `network_error`
**Meaning**: Network connectivity issue (DNS resolution, connection refused, timeout).

**Causes**:
- Mail server host DNS not resolving
- Network packets dropped
- Firewall blocking outbound traffic
- CDN/proxy misconfiguration
- ISP connectivity issue

**Resolution Steps**:
1. Test DNS: `nslookup <mail_host>`
2. Ping mail server: `ping <mail_host>`
3. Verify firewall rules allow outbound to mail server port
4. Check ISP status: contact provider if widespread outage
5. Review network config in application logs
6. Retry after network stabilizes

---

### 5. Operational Errors

#### Category: `retry_exhausted`
**Meaning**: Delivery attempted 3 times (default retry policy) and all failed. Now dropped.

**Causes**:
- Persistent SMTP/network error across retry window
- Recipient permanently invalid
- Configuration critical error

**Resolution Steps**:
1. Review error history in delivery metadata.retry_log in observability dashboard
2. Identify root cause from first attempt error
3. Fix root cause (see sections above for specific error types)
4. If root cause resolved, perform manual retry via dashboard
5. Consider increasing retry count in `config/queue.php` (tries=3) if transient errors common

---

#### Category: `config_error`
**Meaning**: Application configuration invalid (missing mail driver, missing host, etc.).

**Causes**:
- `.env` missing mail settings
- `config/mail.php` referencing non-existent driver
- Mail credentials not set
- Database migration not run

**Resolution Steps**:
1. Verify `.env` has required mail variables: `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
2. Run `php artisan config:cache` to reload config
3. For Laravel app: run `php artisan migrate` if tables missing
4. Restart queue worker if running in background: `php artisan queue:restart`
5. Retry

---

#### Category: `delivery_error` (Generic)
**Meaning**: Delivery failed with unspecified reason or missing error detail.

**Causes**:
- Uncaught exception in delivery pipeline
- Error message lost in logging
- Fallback category for unmapped errors

**Resolution Steps**:
1. Check application logs: `tail -f storage/logs/laravel.log`
2. Search for notification event_key in logs
3. Inspect full exception stack trace
4. Contact platform engineering if error unclear

---

### 6. Delivery Status Outcomes

| Status | Meaning | Manual Retry? | Action |
|--------|---------|---------------|--------|
| `pending` | Queued, awaiting send | No (auto-retrying) | Monitor |
| `sent` | Successfully delivered | No (success) | Archive |
| `failed` | Attempted but error (may retry auto) | Yes (admin manual retry) | Diagnose + Fix + Retry |
| `dropped` | Exhausted retries or permanent error | Yes* (after fixing root cause) | Diagnose + Fix + Retry |

*Dropped retries should only occur after root cause identified and fixed.

---

## Manual Retry Procedure

### When to Use Manual Retry

- Error category resolved (e.g., mail server back online after maintenance)
- Root cause identified and fixed in application/data
- Admin wants to re-attempt a specific failed delivery
- High-priority notification needs immediate re-send

### How to Manually Retry

**Via Observability Dashboard** (recommended):

1. Navigate to `/notification-observability`
2. Use filters to locate failed delivery:
   - Set **Status** = "failed"
   - Set **Hours** to narrow time window
   - Optional: filter by **Channel**, **Event Key**
3. Click target delivery row to open drilldown
4. Click **Retry** button in drilldown modal
5. Confirm retry action
6. Dashboard shows retry submitted; delivery moved to pending queue
7. Check status after ~10 seconds (page auto-refreshes)

**Via API** (for automation):

```bash
curl -X POST https://app.arcav.local/v1/hcm/notifications/delivery/{id}/retry \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json"

# Response on success:
{
  "success": true,
  "data": {
    "id": 123,
    "status": "pending",
    "attemptCount": 4,
    "message": "Delivery marked for retry"
  }
}
```

### Retry Audit Trail

Each manual retry is recorded with:
- **actor_uuid**: Admin UUID who triggered retry
- **actor_email**: Admin email
- **retried_at**: ISO8601 timestamp
- **previous_status**: Status before retry (e.g., "failed")

View audit trail:
1. Observability dashboard → Drilldown → Metadata section
2. Or CSV export (manual retry timestamps in metadata column)
3. Or database query: `SELECT metadata FROM notification_deliveries WHERE id = 123`

---

## Forensic Analysis via CSV Export

### Export Process

1. Navigate to `/notification-observability`
2. Configure filters:
   - **Status**: `sent`, `failed`, or `dropped`
   - **Hours**: Time window (default 24)
   - **Channel**: `database`, `mail`, `sms`, `webhook` (optional)
   - **Event Key**: Specific event (optional)
3. Click **Export CSV** button
4. Browser downloads `notification-deliveries-<timestamp>.csv`

### CSV Columns

| Column | Purpose | Example |
|--------|---------|---------|
| Timestamp | Delivery attempt time (UTC) | 2026-04-24 14:30:45 |
| Event Key | Canonical notification type | `invoice_email_sent` |
| Channel | Delivery medium | `mail` |
| Status | Final delivery status | `failed` |
| Recipient | Recipient identifier | `user@example.com` |
| Attempts | Retry count | `3` |
| Last Error | Most recent error category | `smtp_timeout` |

### Analysis Patterns

#### Pattern 1: Identify Failures by Error Type

```bash
# Count failures by error category
awk -F',' 'NR>1 {print $NF}' export.csv | sort | uniq -c | sort -rn

# Output:
#   15 smtp_timeout
#   8 smtp_auth_failed
#   3 invalid_recipient
```

**Action**: Group by error, apply resolution for each category above.

---

#### Pattern 2: Identify Affected Event Keys

```bash
# List event keys with failures in last export
awk -F',' '$4 ~ /failed/ {print $2}' export.csv | sort | uniq -c

# Output:
#   10 invoice_email_sent
#   5 leave_request_approval_needed
#   2 password_reset
```

**Action**: Review event producer for affected events, check if configuration changed.

---

#### Pattern 3: Identify Systemic Timing

```bash
# Check if failures concentrated in specific hour
awk -F',' '$4 ~ /failed/ {print substr($1, 1, 13)}' export.csv | sort | uniq -c

# Output:
#   12 2026-04-24 14
#   3 2026-04-24 15
```

**Action**: If spike at specific hour, check if mail server maintenance/restart scheduled.

---

#### Pattern 4: High Retry Counts

```bash
# Find deliveries that required many retries (potential flaky issue)
awk -F',' '$6 > 2 {print $0}' export.csv | wc -l

# Output: 5 records needed >2 retries
```

**Action**: These are resilient transient errors. Consider increasing retry policy or backoff delays.

---

## Common Scenarios & Resolutions

### Scenario 1: Sudden Spike in SMTP Timeouts

**Symptoms**:
- CSV export shows `smtp_timeout` errors in last 2 hours
- All channels affected, not isolated

**Diagnosis**:
1. Check mail server status page (external vendor)
2. Verify application connectivity: `telnet mail.server.com 587`
3. Review app logs for network warnings

**Resolution**:
1. If server issue: wait for provider to resolve, then mass-retry via dashboard
2. If app network issue: verify firewall rules, DNS resolution
3. Contact mail provider support if outage ongoing

---

### Scenario 2: All New Employees Not Getting Welcome Email

**Symptoms**:
- CSV export shows `invalid_recipient` errors for new user signups
- Errors started after yesterday's data migration

**Diagnosis**:
1. Check if migration script populated User.email field correctly
2. Query: `SELECT id, email FROM users WHERE email IS NULL OR email = '';`
3. Inspect migration log for warnings/errors

**Resolution**:
1. Fix User.email data at source
2. Manual retry failed deliveries (dashboard → filter by hour → select all → retry)
3. Verify going forward: test welcome email for new signup

---

### Scenario 3: Gmail Recipient Bounces

**Symptoms**:
- CSV shows `bounce_error` for specific gmail.com recipient
- Only 1-2 users affected, not systemic

**Diagnosis**:
1. Contact affected recipient: email may have been deleted
2. Or check recipient's Gmail spam folder
3. Review SPF/DKIM records for sender domain

**Resolution**:
1. If email deleted: update contact info in User record
2. If spam folder: recipient whitelists sender, retry
3. If SPF/DKIM invalid: fix DNS records, wait propagation (24h), retry

---

### Scenario 4: Rate Limit Rejection at 3pm Daily

**Symptoms**:
- CSV export shows pattern: failures spike at 3pm every day
- Error: `rate_limit`
- Same time each day

**Diagnosis**:
1. Check if batch job or report generates high volume at 3pm
2. Verify mail provider rate limit quota vs actual send volume
3. Check provider dashboard for account-level rate limit status

**Resolution**:
1. Stagger notification sends via queue delay
2. Batch notifications in digest mode (future feature)
3. Or upgrade mail provider plan if quota insufficient
4. Retry after load reduces

---

## Performance Tuning

### Queue Worker Optimization

Default retry policy:
- **tries**: 3 (attempt message 3 times)
- **backoff**: [60, 300, 900] (wait 60s, then 5min, then 15min between retries)

To adjust:

```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 900,
        'tries' => 5,      // Increase for more retries
        'timeout' => 60,
    ],
],
```

After change:
```bash
php artisan config:cache
php artisan queue:restart  # Signal workers to reload config
```

---

### Rate Limit Adjustment

If legitimate high-volume sends (e.g., bulk payroll notification):

```php
// routes/api.php
Route::post('/v1/hcm/notifications/delivery/{id}/retry', [HcmNotificationController::class, 'retryDelivery'])
    ->middleware('throttle:100,1');  // Increase from 30 to 100 per minute if needed
```

After change:
```bash
php artisan route:cache
```

---

## Support Escalation

### When to Contact Mail Provider Support

- SSL certificate issues (SMTP TLS errors)
- Server outage (widespread timeout errors)
- Rate limit increase request
- Bounce rate investigation
- Sender reputation issues

### When to Contact Platform Engineering

- Configuration errors in app
- Unclassified delivery errors
- Retry mechanism not working
- Queue worker hanging/stuck
- CSV export performance issues

### Information to Provide

When escalating:
1. **Time window**: "2026-04-24 14:00 UTC to 15:00 UTC"
2. **Error category**: "smtp_timeout"
3. **Affected event**: "invoice_email_sent"
4. **Recipient sample**: "user@example.com"
5. **Attempt count**: "3 retries exhausted"
6. **CSV export**: Attach CSV file with filtered export
7. **Steps taken**: "Verified mail server connectivity, restarted queue worker"

---

## Monitoring & Alerting

### Recommended Dashboards

**Critical Metrics to Monitor**:
- Failed delivery rate (% failed vs total)
- Dropped delivery count (no more retries possible)
- Error distribution by category (pie chart)
- Retry success rate (% successful on retry)
- Average attempts per successful delivery

### Alert Thresholds

- **Critical**: >5% of deliveries failed in last 1 hour
- **Warning**: >2 consecutive failed attempts for same recipient
- **Info**: Rate limit approached (>80% of quota)

### Implementation

Use dashboard filter history and export to build reports:
```bash
# Daily failure rate summary
0 2 * * * cd /app && php artisan notification:export-daily-summary >> logs/notification-summary.log
```

(Future feature: automated alerting endpoint planned)

---

## Appendix: Troubleshooting Decision Tree

```
Observability Dashboard shows failures
  ├─ Check error category (Last Error column)
  │  ├─ smtp_timeout → Mail server unreachable
  │  │   └─ Verify connectivity, contact mail provider
  │  ├─ smtp_auth_failed → Invalid credentials
  │  │   └─ Check .env, refresh token if needed
  │  ├─ smtp_tls_error → SSL negotiation failed
  │  │   └─ Verify cert validity, update TLS config
  │  ├─ invalid_recipient → Bad email address
  │  │   └─ Fix recipient data, don't retry
  │  ├─ bounce_error → Email bounced after send
  │  │   └─ Verify recipient, check sender reputation
  │  ├─ rate_limit → Server rate limit exceeded
  │  │   └─ Wait for reset window, stagger sends
  │  └─ [Other category] → See categories table
  │
  └─ Root cause identified & fixed
      └─ Manual Retry via Dashboard
          └─ Verify status after 10 seconds
              ├─ Status = sent ✓ → Success
              └─ Status = failed again → Escalate
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-04-24 | Initial runbook: error categories, retry procedure, forensic analysis, troubleshooting scenarios |

---

## Quick Links

- [Observability Dashboard](/notification-observability) (Global Admin)
- [API Documentation](/docs/api/notifications-api.md)
- [Feature README](/docs/features/notifications/README.md)
- [Implementation Details](/docs/features/notifications/IMPLEMENTATION.md)
