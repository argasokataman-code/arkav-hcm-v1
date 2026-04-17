/**
 * Authorization Utility
 * 
 * Comprehensive frontend permission & role management for Vue.js.
 * ⚠️ FRONTEND ONLY: Backend ALWAYS enforces via 403/401 responses.
 * Use for UX control (show/hide buttons, disable fields).
 * 
 * Context7 Best Practices:
 * - Composable extraction pattern (toRef, computed, watchers)
 * - Reusable permission checks with caching
 * - Type-safe permission validation
 * - Clear separation: frontend = UX control, backend = enforcement
 */

/**
 * User context structure (typically from auth response)
 * @typedef {Object} UserContext
 * @property {number} id - User ID
 * @property {string} name - User name
 * @property {string} email - User email
 * @property {string} role - Primary role (e.g., 'hcm_admin', 'employee')
 * @property {Array<string>} roles - List of role codes assigned
 * @property {Array<string>} permissions - Flat list of permission codes
 * @property {number|null} activeCompanyId - Currently selected company
 * @property {Array<{id: number, name: string}>} companies - Accessible companies
 */

/**
 * Permission check result
 * @typedef {Object} PermissionCheckResult
 * @property {boolean} allowed - Whether action is permitted
 * @property {string|null} reason - Human-readable reason if denied
 * @property {string|null} errorCode - Error code for logging/analytics
 */

import { ref, computed, watch } from 'vue';

/**
 * Authorization Store
 * Singleton instance holding user context and permissions
 */
class AuthorizationStore {
  constructor() {
    this._userContext = ref(null);
    this._permissionCache = new Map();
    this._cacheExpiry = 5 * 60 * 1000; // 5 min
    this._lastCacheClean = Date.now();
  }

  /**
   * Initialize store with user context (call on app boot)
   * @param {UserContext} userContext
   */
  initialize(userContext) {
    this._userContext.value = userContext || null;
    this._permissionCache.clear();
  }

  /**
   * Get current user context
   * @returns {UserContext|null}
   */
  getUser() {
    return this._userContext.value;
  }

  /**
   * Get current active company ID
   * @returns {number|null}
   */
  getActiveCompanyId() {
    return this._userContext.value?.activeCompanyId ?? null;
  }

  /**
   * Set active company context
   * @param {number} companyId
   */
  setActiveCompanyId(companyId) {
    if (this._userContext.value) {
      this._userContext.value.activeCompanyId = companyId;
      this._permissionCache.clear(); // Invalidate cache on context change
    }
  }

  /**
   * Check if user has specific role
   * @param {string|Array<string>} roleCode - Single role or array of roles
   * @returns {boolean}
   */
  hasRole(roleCode) {
    if (!this._userContext.value) return false;
    const roles = this._userContext.value.roles || [];
    
    if (Array.isArray(roleCode)) {
      return roleCode.some(r => roles.includes(r));
    }
    return roles.includes(roleCode);
  }

  /**
   * Check if user is HCM admin
   * @returns {boolean}
   */
  isHcmAdmin() {
    return this.hasRole('hcm_admin');
  }

  /**
   * Check if user is system admin
   * @returns {boolean}
   */
  isSysAdmin() {
    return this.hasRole('sysadmin');
  }

  /**
   * Check if user has specific permission
   * @param {string|Array<string>} permissionCode - Single permission or array
   * @returns {PermissionCheckResult}
   */
  checkPermission(permissionCode) {
    if (!this._userContext.value) {
      return {
        allowed: false,
        reason: 'User not authenticated',
        errorCode: 'UNAUTHENTICATED'
      };
    }

    const permissions = this._userContext.value.permissions || [];
    const cacheKey = `${permissionCode}`;

    // Check cache
    if (this._permissionCache.has(cacheKey)) {
      const cached = this._permissionCache.get(cacheKey);
      if (Date.now() - cached.timestamp < this._cacheExpiry) {
        return cached.result;
      }
    }

    let result;
    if (Array.isArray(permissionCode)) {
      result = {
        allowed: permissionCode.some(p => permissions.includes(p)),
        reason: result?.allowed ? null : `Missing one of: ${permissionCode.join(', ')}`,
        errorCode: result?.allowed ? null : 'PERMISSION_DENIED'
      };
    } else {
      result = {
        allowed: permissions.includes(permissionCode),
        reason: permissions.includes(permissionCode) ? null : `Permission required: ${permissionCode}`,
        errorCode: permissions.includes(permissionCode) ? null : 'PERMISSION_DENIED'
      };
    }

    // Cache result
    this._permissionCache.set(cacheKey, { result, timestamp: Date.now() });

    return result;
  }

  /**
   * Check if user owns a resource (by userId)
   * @param {number} resourceUserId - User ID of resource owner
   * @returns {boolean}
   */
  isOwner(resourceUserId) {
    return this._userContext.value?.id === resourceUserId;
  }

  /**
   * Check if user can access a company
   * @param {number} companyId
   * @returns {boolean}
   */
  canAccessCompany(companyId) {
    if (!this._userContext.value) return false;
    const companies = this._userContext.value.companies || [];
    return companies.some(c => c.id === companyId);
  }

  /**
   * Get accessible company IDs
   * @returns {Array<number>}
   */
  getAccessibleCompanyIds() {
    if (!this._userContext.value) return [];
    return (this._userContext.value.companies || []).map(c => c.id);
  }

  /**
   * Invalidate permission cache (useful after permission updates)
   */
  invalidateCache() {
    this._permissionCache.clear();
  }
}

// Singleton instance
const store = new AuthorizationStore();

/**
 * Composable: useAuthorization
 * 
 * Usage in Vue 3 Composition API:
 * ```javascript
 * import { useAuthorization } from '@/utils/authorization';
 * 
 * export default {
 *   setup() {
 *     const { isHcmAdmin, canEdit, user, activeCompanyId } = useAuthorization();
 *     
 *     return {
 *       isHcmAdmin,
 *       canEdit,
 *       user,
 *       activeCompanyId
 *     };
 *   }
 * };
 * ```
 */
export function useAuthorization() {
  // Reactive refs
  const user = computed(() => store.getUser());
  const activeCompanyId = computed(() => store.getActiveCompanyId());
  const isAuthenticated = computed(() => !!store.getUser());

  // Role checks
  const isHcmAdmin = computed(() => store.isHcmAdmin());
  const isSysAdmin = computed(() => store.isSysAdmin());

  // Methods
  const hasRole = (roleCode) => store.hasRole(roleCode);
  const checkPermission = (permissionCode) => store.checkPermission(permissionCode);
  const isOwner = (resourceUserId) => store.isOwner(resourceUserId);
  const canAccessCompany = (companyId) => store.canAccessCompany(companyId);
  const getAccessibleCompanyIds = () => store.getAccessibleCompanyIds();
  const setActiveCompanyId = (companyId) => store.setActiveCompanyId(companyId);
  const invalidateCache = () => store.invalidateCache();

  // Permission shortcuts
  const canViewPayroll = computed(() => store.checkPermission('payroll:view').allowed);
  const canFinializePayroll = computed(() => store.checkPermission('payroll:finalize').allowed);
  const canDisbursePayroll = computed(() => store.checkPermission('payroll:disburse').allowed);

  const canViewAttendance = computed(() => store.checkPermission('attendance:view').allowed);
  const canManageAttendance = computed(() => store.checkPermission('attendance:manage').allowed);

  const canViewLeave = computed(() => store.checkPermission('leave:view').allowed);
  const canApproveLeave = computed(() => store.checkPermission('leave:approve').allowed);

  const canViewAssets = computed(() => store.checkPermission('assets:view').allowed);
  const canManageAssets = computed(() => store.checkPermission('assets:manage').allowed);

  const canViewUsers = computed(() => store.checkPermission('users:view').allowed);
  const canManageUsers = computed(() => store.checkPermission('users:manage').allowed);

  return {
    // Core state
    user,
    activeCompanyId,
    isAuthenticated,

    // Role checks
    isHcmAdmin,
    isSysAdmin,
    hasRole,

    // Permission checks
    checkPermission,
    isOwner,
    canAccessCompany,
    getAccessibleCompanyIds,

    // Company context
    setActiveCompanyId,

    // Cache management
    invalidateCache,

    // Shortcuts: Payroll
    canViewPayroll,
    canFinializePayroll,
    canDisbursePayroll,

    // Shortcuts: Attendance
    canViewAttendance,
    canManageAttendance,

    // Shortcuts: Leave
    canViewLeave,
    canApproveLeave,

    // Shortcuts: Assets
    canViewAssets,
    canManageAssets,

    // Shortcuts: Users
    canViewUsers,
    canManageUsers
  };
}

/**
 * Helper: Initialize authorization on app boot
 * Call this in main.js or auth middleware
 * 
 * @param {UserContext} userContext
 */
export function initializeAuthorization(userContext) {
  store.initialize(userContext);
}

/**
 * Helper: Get user context (non-reactive)
 * Useful for non-Vue contexts
 */
export function getAuthUser() {
  return store.getUser();
}

/**
 * Helper: Route guard for authorization
 * Usage in router:
 * ```javascript
 * router.beforeEach((to, from, next) => {
 *   if (to.meta.requiresAuth && !getAuthUser()) {
 *     next('/login');
 *   } else if (to.meta.requiresRole && !hasRoleSync(to.meta.requiresRole)) {
 *     next('/forbidden');
 *   } else {
 *     next();
 *   }
 * });
 * ```
 */
export function hasRoleSync(roleCode) {
  return store.hasRole(roleCode);
}

/**
 * Helper: Export store for direct access if needed
 * (Use sparingly; prefer composables for Vue components)
 */
export function getAuthorizationStore() {
  return store;
}

export default {
  initializeAuthorization,
  getAuthUser,
  useAuthorization,
  hasRoleSync,
  getAuthorizationStore
};
