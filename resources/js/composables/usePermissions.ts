import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export interface User {
    id: number
    name: string
    email: string
    role?: {
        id: number
        name: string
        display_name: string
    }
    permissions?: string[]
}

export function usePermissions() {
    const page = usePage()
    
    const user = computed(() => page.props.auth?.user as User)
    const permissions = computed(() => (page.props.permissions as Record<string, boolean>) || {})
    
    // Check if user has a specific permission
    const hasPermission = (permission: string): boolean => {
        return user.value?.permissions?.includes(permission) || false
    }
    
    // Check if user has any of the given permissions
    const hasAnyPermission = (permissionList: string[]): boolean => {
        return permissionList.some(permission => hasPermission(permission))
    }
    
    // Check if user has all of the given permissions
    const hasAllPermissions = (permissionList: string[]): boolean => {
        return permissionList.every(permission => hasPermission(permission))
    }
    
    // Check if user has a specific role
    const hasRole = (role: string): boolean => {
        return user.value?.role?.name === role
    }
    
    // Check if user has any of the given roles
    const hasAnyRole = (roles: string[]): boolean => {
        return roles.includes(user.value?.role?.name || '')
    }
    
    // Get permission from page props (for specific page permissions)
    const can = (permission: string): boolean => {
        return permissions.value[permission] || false
    }
    
    // Permission shortcuts for common checks
    const canViewPayslips = computed(() => hasPermission('payslip.view'))
    const canCreatePayslips = computed(() => hasPermission('payslip.create'))
    const canDeletePayslips = computed(() => hasPermission('payslip.delete'))
    const canViewAllPayslips = computed(() => hasPermission('payslip.view_all'))
    const canProcessPayslips = computed(() => hasPermission('payslip.process'))
    
    const canViewKoperasi = computed(() => hasPermission('koperasi.view'))
    const canCreateKoperasi = computed(() => hasPermission('koperasi.create'))
    const canUpdateKoperasi = computed(() => hasPermission('koperasi.update'))
    const canDeleteKoperasi = computed(() => hasPermission('koperasi.delete'))
    const canManageKoperasiRules = computed(() => hasPermission('koperasi.manage_rules'))
    
    const canViewUsers = computed(() => hasPermission('user.view'))
    const canCreateUsers = computed(() => hasPermission('user.create'))
    const canUpdateUsers = computed(() => hasPermission('user.update'))
    const canDeleteUsers = computed(() => hasPermission('user.delete'))
    const canActivateUsers = computed(() => hasPermission('user.activate'))
    const canAssignRoles = computed(() => hasPermission('user.assign_roles'))
    
    const canViewSystemHealth = computed(() => hasPermission('system.view_health'))
    const canViewStatistics = computed(() => hasPermission('system.view_statistics'))
    const canClearCache = computed(() => hasPermission('system.clear_cache'))
    const canOptimizeDatabase = computed(() => hasPermission('system.optimize_database'))
    const canSystemCleanup = computed(() => hasPermission('system.cleanup'))
    const canClearLogs = computed(() => hasPermission('system.clear_logs'))
    const canManageSettings = computed(() => hasPermission('system.manage_settings'))
    
    const canViewAnalytics = computed(() => hasPermission('analytics.view'))
    const canExportAnalytics = computed(() => hasPermission('analytics.export'))
    const canGenerateReports = computed(() => hasPermission('report.generate'))
    
    const canViewQueue = computed(() => hasPermission('queue.view'))
    const canManageQueue = computed(() => hasPermission('queue.manage'))
    const canClearQueue = computed(() => hasPermission('queue.clear'))
    
    // Role shortcuts
    const isSuperAdmin = computed(() => hasRole('super_admin'))
    const isAdmin = computed(() => hasRole('admin'))
    const isManager = computed(() => hasRole('manager'))
    const isOperator = computed(() => hasRole('operator'))
    const isUser = computed(() => hasRole('user'))
    
    const isAdminLevel = computed(() => hasAnyRole(['super_admin', 'admin']))
    const isManagerLevel = computed(() => hasAnyRole(['super_admin', 'admin', 'manager']))
    
    return {
        user,
        permissions,
        
        // Core permission functions
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        hasRole,
        hasAnyRole,
        can,
        
        // Payslip permissions
        canViewPayslips,
        canCreatePayslips,
        canDeletePayslips,
        canViewAllPayslips,
        canProcessPayslips,
        
        // Koperasi permissions
        canViewKoperasi,
        canCreateKoperasi,
        canUpdateKoperasi,
        canDeleteKoperasi,
        canManageKoperasiRules,
        
        // User permissions
        canViewUsers,
        canCreateUsers,
        canUpdateUsers,
        canDeleteUsers,
        canActivateUsers,
        canAssignRoles,
        
        // System permissions
        canViewSystemHealth,
        canViewStatistics,
        canClearCache,
        canOptimizeDatabase,
        canSystemCleanup,
        canClearLogs,
        canManageSettings,
        
        // Analytics permissions
        canViewAnalytics,
        canExportAnalytics,
        canGenerateReports,
        
        // Queue permissions
        canViewQueue,
        canManageQueue,
        canClearQueue,
        
        // Role shortcuts
        isSuperAdmin,
        isAdmin,
        isManager,
        isOperator,
        isUser,
        isAdminLevel,
        isManagerLevel,
    }
} 