# ClientFlow

**Professional proposal, payment, and client management for freelancers and agencies.**

> Sprint 0 — Entitlements Foundation

---

## Overview

ClientFlow is a single WordPress plugin with a modular internal architecture. Every feature check routes through one permission engine (`cf_can_user()`), ensuring consistent behaviour and no scattered plan checks across modules.

### Tier Summary

| Feature | Free | Pro | Agency |
|---|---|---|---|
| Proposals | 5 (lifetime) | Unlimited | Unlimited |
| Payments | — | ✓ | ✓ |
| Client Portal | — | Basic (view) | Full |
| Projects | — | — | ✓ |
| Messaging | — | — | ✓ |
| File Uploads | — | — | 1 GB |
| AI Requests/mo | 0 | 100 | 500 |
| Team Users | 1 | 1 | 5 |

---

## Sprint 0 Deliverables

| File | Purpose |
|---|---|
| `clientflow.php` | Plugin bootstrap, autoloader, activation hooks |
| `includes/class-entitlements.php` | Core permission engine |
| `database/schema.php` | All 10 database tables via `dbDelta()` |
| `rest-api/entitlements.php` | REST API: `/user/plan`, `/user/can`, `/user/usage`, `/user/log-usage`, `/admin/usage-report` |
| `tests/test-entitlements.php` | PHPUnit test suite (30+ test cases) |
| `tests/bootstrap.php` | PHPUnit bootstrap |
| `admin/views/plan-overview.php` | Plan & Usage admin dashboard UI |
| `package.json` | Node.js / `@wordpress/scripts` configuration |
| `.wp-env.json` | Local development environment (wp-env) |
| `phpunit.xml.dist` | PHPUnit configuration |
| `.eslintrc.json` | ESLint configuration |

---

## Requirements

- **PHP** 8.0+
- **WordPress** 6.0+
- **Node.js** 18+ (for JS builds in later sprints)
- **Composer** (for PHP dependencies / test runner)

---

## Local Development Setup

### 1. Start the local environment

```bash
npm install
npx wp-env start
```

This spins up WordPress on `http://localhost:8888` with the plugin active.

### 2. Run PHP tests

Install the WordPress test suite:

```bash
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Then run the suite:

```bash
./vendor/bin/phpunit --testdox
```

### 3. JS linting (Sprint 1+)

```bash
npm run lint:js
npm run build
```

---

## Architecture

### Permission Engine

All feature access flows through a single function:

```php
cf_can_user( int $user_id, string $feature, array $options = [] ): bool|string
```

**Never add `if ($plan === 'pro')` checks in module handlers.** Always call `cf_can_user()`.

### Feature Slugs

| Slug | Returns |
|---|---|
| `create_proposal` | `bool` |
| `use_ai` | `bool` |
| `use_payments` | `bool` |
| `use_portal` | `false` \| `'basic'` \| `'full'` |
| `use_projects` | `bool` |
| `use_messaging` | `bool` |
| `use_files` | `bool` |
| `team_access` | `bool` |

### Class Reference

```php
// Check permission
ClientFlow_Entitlements::can_user( $user_id, 'use_ai' );

// Get user plan
ClientFlow_Entitlements::get_user_plan( $user_id ); // 'free'|'pro'|'agency'

// Get monthly usage
ClientFlow_Entitlements::get_monthly_usage( $user_id, 'use_ai' );

// Get total lifetime count
ClientFlow_Entitlements::get_total_count( $user_id, 'create_proposal' );

// Get feature limit (null = unlimited, 0 = blocked)
ClientFlow_Entitlements::get_feature_limit( $user_id, 'use_ai' );

// Log usage
ClientFlow_Entitlements::log_usage( $user_id, 'use_ai', [
    'proposal_id'   => 42,
    'action'        => 'improve',
    'tokens_input'  => 120,
    'tokens_output' => 80,
    'cost_usd'      => 0.012,
] );

// Check AI rate limit (max 1 req / 3 sec)
ClientFlow_Entitlements::check_rate_limit( $user_id );

// Set plan (handles downgrade notification)
ClientFlow_Entitlements::set_user_plan( $user_id, 'pro', 'free' );
```

---

## REST API

Base namespace: `/wp-json/clientflow/v1/`

All routes require a logged-in WordPress user (nonce via `X-WP-Nonce` header).

| Method | Route | Description |
|---|---|---|
| `GET` | `/user/plan` | Current user's plan + limits |
| `POST` | `/user/can` | Check feature access. Body: `{"feature":"use_ai"}` |
| `GET` | `/user/usage` | Live usage statistics |
| `POST` | `/user/log-usage` | Log a feature usage event |
| `GET` | `/admin/usage-report` | AI cost report (admin only). Query: `?month=2026-04` |

### Example: Check if user can use AI

```bash
curl -X POST https://yoursite.com/wp-json/clientflow/v1/user/can \
  -H "X-WP-Nonce: <nonce>" \
  -H "Content-Type: application/json" \
  -d '{"feature":"use_ai"}'
```

Response:
```json
{
  "feature": "use_ai",
  "allowed": true,
  "tier": null,
  "plan": "pro"
}
```

---

## Database Tables

All tables are prefixed with the WordPress `$wpdb->prefix` (typically `wp_`).

1. `clientflow_user_meta` — plan, usage counters, billing info
2. `clientflow_ai_usage_logs` — AI request audit trail + cost tracking
3. `clientflow_clients` — client records
4. `clientflow_proposals` — proposal data
5. `clientflow_projects` — post-acceptance project tracking (Agency)
6. `clientflow_payments` — Stripe payment records
7. `clientflow_messages` — threaded messaging (Agency)
8. `clientflow_files` — file uploads per project (Agency)
9. `clientflow_approvals` — approval workflows (Agency)
10. `clientflow_events` — analytics event log

---

## Monthly Reset Cron

AI and proposal monthly counters reset automatically at the start of each month via the `clientflow_monthly_reset` WP-Cron action. This calls `ClientFlow_Entitlements::reset_monthly_usage()`.

---

## Rate Limiting

AI requests are rate-limited to **1 request per 3 seconds per user** via WordPress transients. The `/user/log-usage` endpoint returns HTTP 429 if the limit is exceeded.

Monthly hard stops also apply:
- Pro: 100 AI requests/month → HTTP 429 on the 101st
- Agency: 500 AI requests/month → HTTP 429 on the 501st

---

## Next Sprint

**Sprint 1: Proposal Module**
- Proposal builder UI (React + Gutenberg blocks)
- Template system (3 free, 10 pro/agency)
- Save / edit / duplicate
- All endpoints call `cf_can_user()` before acting

---

## Changelog

### 0.1.0 — Sprint 0
- Entitlements engine (`ClientFlow_Entitlements`)
- All 10 database tables
- REST API (5 endpoints)
- Plan & Usage admin dashboard
- PHPUnit test suite (30+ tests)
# clientflow
