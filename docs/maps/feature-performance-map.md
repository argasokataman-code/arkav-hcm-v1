# Feature Map: Performance Management

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/performance` | `HcmPerformanceController` | `performance.view` / `performance.manage` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Performance/HcmPerformanceController.php` — Main controller

## 3. Controller Concerns
- `HandlesPerformanceCycles` — Cycle management (quarterly/annual)
- `HandlesPerformanceGoals` — Goal setting & tracking
- `HandlesPerformanceIndicators` — KPI definitions
- `HandlesPerformanceReviews` — Review submission & scoring

## 4. Models
- `App\Models\PerformanceCycle` — Review cycle (period, status)
- `App\Models\PerformanceGoal` — Employee goals
- `App\Models\PerformanceGoalType` — Goal categories
- `App\Models\PerformanceIndicatorItem` — Indicator items
- `App\Models\PerformanceIndicatorTemplate` — Reusable templates
- `App\Models\PerformanceReview` — Review record
- `App\Models\PerformanceReviewScore` — Scores per indicator

## 5. Key Relations
```
PerformanceCycle (1) -> (N) PerformanceReview
PerformanceReview (1) -> (N) PerformanceReviewScore
PerformanceReview -> User (employee)
PerformanceGoal -> PerformanceGoalType
PerformanceGoal -> User
```

## 6. Notifications
- `PerformanceReviewCreatedNotification`
- `PerformanceReviewSubmittedNotification`
- `PerformanceReviewManagerReviewedNotification`
- `PerformanceReviewFinalizedNotification`

## 7. Tests
- E2E: `backend/tests/` — cari `*Performance*`
- Seeder: `backend/database/seeders/PerformanceSeeder.php`
