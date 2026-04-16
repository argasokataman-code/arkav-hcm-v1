# Frontend Authorization & Permissions Guide

## Overview

The `auth-permissions-utils.js` utility provides a **consistent, unified approach** to role-based access control (RBAC) across the frontend. This replaces scattered, inconsistent permission checks throughout the codebase.

**CRITICAL:** Frontend checks are for **UX only** - they hide/disable UI elements. Backend APIs MUST also enforce the same rules via 403/401 responses.

## Quick Start

### Import/Load

The utility is automatically loaded in the page footer and available globally as `window.AuthPermissions`.

```javascript
// Access methods directly
if (window.isHcmAdmin()) {
    // Show admin-only UI
}

// Or use the namespace
if (window.AuthPermissions.canEditResource(resourceData)) {
    // Show edit button
}
```

### Common Patterns

#### 1. Check if User is Admin

```javascript
if (window.isHcmAdmin()) {
    // Show admin controls
    $('button.admin-action').removeClass('d-none');
}
```

#### 2. Check if User Can Edit Resource

```javascript
const resource = { id: 1, userId: 42, name: 'Example' };
if (window.canEditResource(resource)) {
    $('#edit-button').prop('disabled', false);
}
```

#### 3. Check if Feature is Available

```javascript
const subscription = { status: 'active', features: ['payroll', 'performance'] };
if (window.hasFeatureAccess('payroll', subscription)) {
    $('#payroll-module').show();
} else {
    $('#upgrade-prompt').show();
}
```

#### 4. Handle API Authorization Errors

```javascript
window.AuthApi.request('GET', '/api/resource/123')
    .catch(error => {
        window.AuthPermissions.handleAuthorizationError(
            error,
            () => console.log('Not authenticated'),
            () => console.log('Not authorized')
        );
    });
```

## API Reference

### `isHcmAdmin()`

Returns `true` if user is a global HCM administrator.

```javascript
if (window.isHcmAdmin()) {
    // Can perform admin operations
}
```

### `isAuthenticated()`

Returns `true` if user is logged in.

```javascript
if (!window.AuthPermissions.isAuthenticated()) {
    window.location.href = '/login';
}
```

### `isOwner(resourceUserId)`

Returns `true` if current user owns a resource (by user ID).

```javascript
const canDelete = window.isOwner(comment.userId);
```

### `canEditResource(resourceData)`

Determines if user can edit a resource. Uses priority-based logic:

1. **Server-provided `canEdit`** in resourceData (MOST TRUSTED)
2. User is HCM admin
3. User owns the resource
4. User is manager of owner

```javascript
const resource = {
    id: 1,
    userId: 42,
    name: 'Task',
    canEdit: true,  // Server explicitly says yes
};

if (window.canEditResource(resource)) {
    $('#edit-button').show();
}
```

### `canDeleteResource(resourceData)`

Determines if user can delete a resource.

1. **Server-provided `canDelete`** (MOST TRUSTED)
2. User is HCM admin only (no owner deletion)

```javascript
if (window.canDeleteResource(resource)) {
    $('#delete-button').show();
}
```

### `canViewResource(resourceData)`

Determines if user can view a resource.

1. **Server-provided `canAccess`**
2. User is HCM admin
3. User owns the resource
4. User is manager of owner

### `hasFeatureAccess(featureName, subscriptionData)`

Checks if feature is available in subscription.

```javascript
if (window.hasFeatureAccess('payroll', subscriptionData)) {
    $('#payroll-nav').show();
}
```

Features: `'payroll'`, `'performance'`, `'asset_management'`, `'tickets'`, etc.

### `hasPermission(permission, contextData)`

Checks if user has a specific permission.

```javascript
// Admin has all permissions
if (window.AuthPermissions.hasPermission('manage_users')) {
    // Allow user management
}
```

## Usage Patterns

### Pattern 1: Show/Hide UI Elements Based on Role

**OLD (scattered checks):**
```javascript
// subscriptions-management.js
const isAdmin = window.AuthUser.isHcmAdmin;
if (isAdmin) {
    document.querySelectorAll('[data-admin-only]').forEach(el => el.classList.remove('d-none'));
}
```

**NEW (unified):**
```javascript
// Any module
if (window.isHcmAdmin()) {
    document.querySelectorAll('[data-admin-only]').forEach(el => el.classList.remove('d-none'));
}
```

### Pattern 2: Enable/Disable Actions

**OLD (mixed patterns):**
```javascript
// performance-data.js
const canDelete = isOwner || isManager || isAdmin;
$('[data-delete-btn]').prop('disabled', !canDelete);

// resignationSubmissions.js  
const canEdit = user.id === resignation.userId;
editButton.disabled = !canEdit;
```

**NEW (consistent):**
```javascript
// Both cases
if (window.canDeleteResource(resource)) {
    $('#delete-btn').prop('disabled', false);
}
```

### Pattern 3: Feature Gates (Subscriptions)

**OLD (inconsistent):**
```javascript
// payroll-data.js
if (packageFeatures?.includes('payroll')) { /* ... */ }

// performance-data.js
if (subscription.features.payroll) { /* ... */ }
```

**NEW (unified):**
```javascript
// Single approach
if (window.hasFeatureAccess('payroll', subscriptionData)) {
    renderPayrollModule();
}
```

### Pattern 4: Server-Provided Permissions (SAFEST)

The SAFEST approach is to let the server compute permissions and include them in API responses:

```javascript
// GET /api/goals/123 response
{
    "success": true,
    "data": {
        "id": 123,
        "title": "Q4 Goals",
        "userId": 42,
        "managerId": 10,
        "canEdit": true,      // Server computed this
        "canDelete": false,    // Server computed this
        "canAccess": true      // Server computed this
    }
}
```

Then in frontend:

```javascript
// Trust server computation
const goal = response.data;
if (goal.canEdit) {
    $('#edit-button').show();
}
if (goal.canDelete) {
    $('#delete-button').show();
}
```

## Refactoring Checklist

When updating existing code:

- [ ] Replace `user.isHcmAdmin` with `window.isHcmAdmin()`
- [ ] Replace custom `canEdit` logic with `window.canEditResource(resource)`
- [ ] Replace custom `canDelete` logic with `window.canDeleteResource(resource)`
- [ ] Replace feature checks with `window.hasFeatureAccess(feature, subscription)`
- [ ] Remove duplicate permission computation logic
- [ ] Add `canEdit`/`canDelete`/`canAccess` to API responses when applicable
- [ ] Test that buttons/actions are properly hidden based on role

## Testing

### Unit Tests (future)

```javascript
// Test: Non-admin cannot edit another user's resource
const resource = { userId: 42, canEdit: undefined };
AuthPermissions.isHcmAdmin = () => false;
AuthPermissions.isOwner = () => false;
assert.equal(canEditResource(resource), false);

// Test: Admin can edit anything
AuthPermissions.isHcmAdmin = () => true;
assert.equal(canEditResource(resource), true);
```

### Manual Testing

1. **Admin View:**
   - Log in as HCM Admin
   - Verify all admin buttons visible
   - Verify can edit/delete any resource

2. **Employee View:**
   - Log in as regular employee
   - Verify own resource can be edited
   - Verify other employee's resource cannot be edited
   - Verify feature gates work based on subscription

3. **No Subscription:**
   - View page without active subscription
   - Verify feature-gated UI hidden
   - Verify modal prompts to upgrade

## Troubleshooting

### "AuthPermissions is undefined"

- Ensure `auth-permissions-utils.js` is loaded in footer-scripts.blade.php
- Check browser console for script errors
- Verify script path is correct

### Permissions work on frontend but API returns 403

- Frontend checks are UX only - backend must also enforce
- Add permission checks to controller methods
- Return 403 if user lacks permission
- See `app/Http/Controllers/Api/` for examples

### User sees button but can't perform action

- Backend API isn't enforcing the same permission rules
- Frontend says `canEdit: true` but backend returned 403
- Audit backend controller to add permission checks

## Migration Guide

### Old Code (Multiple Locations)

```javascript
// attendance-data.js
const isAdmin = window.AuthUser?.isHcmAdmin === true;

// performance-data.js
function canEditGoal(goal) {
    return isCurrentUser(goal.userId) || isManagerOf(goal.managerId) || isAdmin;
}

// users-management.js
const userPermissions = fetchPermissionsFromUI();
```

### New Code (Unified)

```javascript
// Same pattern everywhere
if (window.isHcmAdmin()) {
    // Admin action
}

if (window.canEditResource(goal)) {
    // Edit action
}

// Server provides permissions
const response = await AuthApi.request('GET', '/api/goals/123');
const goal = response.data;
if (goal.canEdit) {
    // Show edit UI
}
```

## See Also

- `.cursor/rules/application-security-baseline.mdc` — Security baseline
- `.cursor/rules/web-hcm-route-security.mdc` — Route security patterns
- `docs/planning/active-hcm-templates-and-permissions.md` — Current RBAC matrix
