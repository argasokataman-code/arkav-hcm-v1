# Feature Map: Termination, Resignation & Promotion

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET/POST | `/v1/hcm/terminations` | `HcmTerminationController` | `termination.manage` |
| GET/POST | `/v1/hcm/resignations` | `HcmResignationController` | `resignation.manage` |
| GET/POST | `/v1/hcm/promotions` | `HcmPromotionController` | `promotion.manage` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Termination/HcmTerminationController.php`
- `backend/app/Http/Controllers/Api/Resignation/HcmResignationController.php`
- `backend/app/Http/Controllers/Api/Promotion/HcmPromotionController.php`

## 3. Termination Concerns
- `HandlesTerminationCrud` — CRUD operations
- `HandlesTerminationSettlementCalculation` — Severance calculation
- `HandlesTerminationSettlementPreview` — Preview before finalize

## 4. Models
- `App\Models\HcmTermination` — Termination record
- `App\Models\HcmTerminationChecklistItem` — Checklist items
- `App\Models\HcmResignation` — Resignation requests
- `App\Models\HcmPromotion` — Promotion records
- `App\Models\EmployeeCompensation` — Compensation history (for severance calc)

## 5. Services
- `backend/app/Services/Hcm/TerminationSettlementCalculationService.php` — Severance calculation
- `backend/app/Services/Hcm/TerminationWorkflowValidator.php` — Workflow validation
- `backend/app/Services/Hcm/PkwtCompensationService.php` — PKWT compensation

## 6. Config
- `backend/config/termination-policy-profiles.php` — Policy profiles for different termination types

## 7. Key Relations
```
HcmTermination -> User (employee)
HcmTermination -> HcmTerminationChecklistItem (1:N)
HcmResignation -> User (employee)
HcmPromotion -> User (employee)
```

## 8. Notifications
- `TerminationApprovalRequestedNotification`
- `ResignationApprovalRequestedNotification`
