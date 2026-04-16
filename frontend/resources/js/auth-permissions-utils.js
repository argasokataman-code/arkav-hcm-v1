/**
 * Frontend Authorization/Permissions Utility
 * 
 * Provides consistent role-based access control across the application.
 * This utility should be used for:
 * 1. Conditional rendering (show/hide UI elements)
 * 2. Disabling actions (disable buttons/forms)
 * 3. Early permission checks before API calls
 * 
 * IMPORTANT: Backend APIs MUST also enforce these rules via 403/401 responses.
 * Frontend checks are for UX only - never trust frontend-only authorization.
 */

(function (window) {
    "use strict";

    /**
     * Get current user context from window or localStorage
     */
    function getUserContext() {
        // Try to get from window first (injected by server)
        if (window.AuthUser) {
            return window.AuthUser;
        }

        // Try localStorage as fallback
        try {
            const stored = localStorage.getItem("auth_user");
            if (stored) {
                return JSON.parse(stored);
            }
        } catch (_e) {}

        return null;
    }

    /**
     * Check if user is HCM Admin (global super-admin role)
     */
    function isHcmAdmin() {
        const user = getUserContext();
        return !!(user && user.isHcmAdmin === true);
    }

    /**
     * Check if user is authenticated
     */
    function isAuthenticated() {
        return !!getUserContext();
    }

    /**
     * Check if user owns a resource (by user_id)
     */
    function isOwner(resourceUserId) {
        const user = getUserContext();
        return !!(user && user.id && user.id === resourceUserId);
    }

    /**
     * Check if user is manager of another user
     * (Would require additional context from API response)
     */
    function isManagerOf(targetUserId, managerIdFromApi) {
        const user = getUserContext();
        if (!user || !user.id) return false;

        // If server provided manager check in API response, trust it
        if (managerIdFromApi !== undefined) {
            return user.id === managerIdFromApi;
        }

        // Otherwise, would need to check against a role/permission from API
        // For now, return false - let API handle it
        return false;
    }

    /**
     * Check if user has a specific permission
     * @param {string} permission - Permission code (e.g., 'edit_payroll', 'manage_users')
     * @param {object} contextData - Additional context (from API, component state)
     */
    function hasPermission(permission, contextData = {}) {
        if (isHcmAdmin()) {
            return true; // Admins have all permissions
        }

        const user = getUserContext();
        if (!user) return false;

        // Check against user role/permissions (if server provides them)
        if (user.permissions && Array.isArray(user.permissions)) {
            return user.permissions.includes(permission);
        }

        // Check against server-provided permission in contextData
        // (This is the safest approach - let server compute permissions)
        if (contextData && contextData.canEdit !== undefined) {
            return contextData.canEdit === true;
        }
        if (contextData && contextData.canDelete !== undefined) {
            return contextData.canDelete === true;
        }
        if (contextData && contextData.canAccess !== undefined) {
            return contextData.canAccess === true;
        }

        return false;
    }

    /**
     * Determine if user can edit a resource
     * 
     * Priority:
     * 1. Server-provided canEdit in context (MOST TRUSTED)
     * 2. User is HCM Admin
     * 3. User owns the resource
     * 4. User is manager of owner (if provided)
     */
    function canEditResource(resourceData = {}) {
        // Trust server computation first
        if (resourceData.canEdit !== undefined) {
            return resourceData.canEdit === true;
        }

        const user = getUserContext();
        if (!user) return false;

        // Admins can edit anything
        if (isHcmAdmin()) return true;

        // Owner can edit their own resource
        if (resourceData.userId && isOwner(resourceData.userId)) {
            return true;
        }

        // Manager can edit subordinate's resource
        if (resourceData.managerId && isOwner(resourceData.managerId)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete a resource
     */
    function canDeleteResource(resourceData = {}) {
        // Trust server computation first
        if (resourceData.canDelete !== undefined) {
            return resourceData.canDelete === true;
        }

        // Only admins can delete
        return isHcmAdmin();
    }

    /**
     * Determine if user can view a resource
     */
    function canViewResource(resourceData = {}) {
        // Trust server computation first
        if (resourceData.canAccess !== undefined) {
            return resourceData.canAccess === true;
        }

        const user = getUserContext();
        if (!user) return false;

        // Admins can view everything
        if (isHcmAdmin()) return true;

        // Owner can view their resource
        if (resourceData.userId && isOwner(resourceData.userId)) {
            return true;
        }

        // Manager can view subordinate's resource
        if (resourceData.managerId && isOwner(resourceData.managerId)) {
            return true;
        }

        return false;
    }

    /**
     * Check if feature is available in current subscription
     * @param {string} featureName - Feature code (e.g., 'tickets', 'payroll')
     * @param {object} subscriptionData - Subscription info from API
     */
    function hasFeatureAccess(featureName, subscriptionData = {}) {
        if (isHcmAdmin()) {
            return true; // Admins have all features
        }

        if (!subscriptionData) return false;

        // Check if subscription is active and has feature
        if (subscriptionData.status === 'active' || subscriptionData.status === 'trial') {
            return !!(
                subscriptionData.features &&
                Array.isArray(subscriptionData.features) &&
                subscriptionData.features.includes(featureName)
            );
        }

        return false;
    }

    /**
     * Handle API authorization error (401/403)
     * @param {object} error - Error response from API
     * @param {function} onUnauthorized - Callback for 401 (not authenticated)
     * @param {function} onForbidden - Callback for 403 (authenticated but not authorized)
     */
    function handleAuthorizationError(error, onUnauthorized, onForbidden) {
        if (!error) return;

        const status = error.status || (error.response && error.response.status);

        if (status === 401) {
            if (onUnauthorized) onUnauthorized(error);
            // Redirect to login
            window.location.href = "/login";
        } else if (status === 403) {
            if (onForbidden) onForbidden(error);
            // Show permission denied message
            const message = error.data?.error?.message || "Permission denied";
            console.error("Authorization error:", message);
        }
    }

    // Export to window
    window.AuthPermissions = {
        isHcmAdmin,
        isAuthenticated,
        isOwner,
        isManagerOf,
        hasPermission,
        canEditResource,
        canDeleteResource,
        canViewResource,
        hasFeatureAccess,
        handleAuthorizationError,
        getUserContext,
    };

    // Also make available as global functions for convenience
    window.isHcmAdmin = isHcmAdmin;
    window.canEditResource = canEditResource;
    window.canDeleteResource = canDeleteResource;
    window.canViewResource = canViewResource;
    window.hasFeatureAccess = hasFeatureAccess;
})(window);
