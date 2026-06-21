# Feature Map: Overtime, Asset, Training, Ticket, Notes, Calendar

## 1. Overtime
- **Controller:** `backend/app/Http/Controllers/Api/Overtime/HcmOvertimeRequestController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Overtime/HcmOvertimeTypeController.php`
- **Models:** `OvertimeRequest`, `HcmOvertimeType`
- **Service:** `backend/app/Services/Hcm/OvertimePayCalculator.php`
- **Notifications:** `OvertimeApprovalRequestedNotification`

## 2. Asset Management
- **Controller:** `backend/app/Http/Controllers/Api/Asset/HcmAssetController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Asset/HcmAssetCategoryController.php`
- **Models:** `Asset`, `AssetAssignment`, `AssetCategory`, `AssetLog`, `AssetAttachment`
- **Service:** `backend/app/Services/AssetService.php`
- **Notifications:** `AssetAssignedNotification`, `AssetReturnedNotification`
- **Middleware:** `EnsureAssetManagementWebAccess`

## 3. Training
- **Controller:** `backend/app/Http/Controllers/Api/Training/HcmTrainingController.php`
- **Models:** `HcmTraining`, `HcmTrainingType`, `HcmTrainer`

## 4. Ticket
- **Controller:** `backend/app/Http/Controllers/Api/Ticket/HcmTicketController.php`
- **Models:** `Ticket`, `TicketCategory`, `TicketComment`, `TicketAttachment`, `TicketAssignmentHistory`
- **Notifications:** `TicketCreatedNotification`, `TicketAssignedNotification`, `TicketClosedNotification`, `TicketResolvedNotification`

## 5. Notes
- **Controller:** `backend/app/Http/Controllers/Api/Notes/HcmNoteController.php`
- **Model:** `Note`

## 6. Calendar
- **Controller:** `backend/app/Http/Controllers/Api/Calendar/HcmCalendarEventController.php`
- **Model:** `CalendarEvent`

## 7. Dashboard & AI
- **Controller:** `backend/app/Http/Controllers/Api/Dashboard/HcmDashboardController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Dashboard/HcmAiChatController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Dashboard/HcmGlobalSearchController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Dashboard/HcmActivityController.php`
- **AI Services:** `backend/app/Services/Ai/AiLlmService.php`, `AiIntentClassifier.php`, `AiIntentResolver.php`
- **Model:** `AiChatLog`, `DashboardMetric`, `HcmActivityLog`

## 8. Notifications
- **Controller:** `backend/app/Http/Controllers/Api/Notifications/HcmNotificationController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Notifications/HcmNotificationPreferenceController.php`
- **Models:** `DatabaseNotification`, `NotificationPreference`, `NotificationDelivery`
- **Service:** `backend/app/Services/NotificationService.php`, `NotificationDeliveryRecorder.php`

## 9. Reports & Reconciliation
- **Controller:** `backend/app/Http/Controllers/Api/Reports/ReportController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Reports/ReportSnapshotController.php`
- **Controller:** `backend/app/Http/Controllers/Api/Reconciliation/ReconciliationExportController.php`
- **Models:** `ReportDataBlock`, `ReportExport`, `ReportFilter`, `ReportSnapshot`, `ExportReconciliationEvidence`
- **Service:** `backend/app/Services/Reporting/ReportSnapshotService.php`
- **Service:** `backend/app/Services/Reconciliation/ReconciliationExportService.php`, `ReconciliationGateService.php`
