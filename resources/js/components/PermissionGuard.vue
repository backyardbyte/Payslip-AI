<template>
    <slot v-if="hasAccess" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'

interface Props {
    permission?: string
    permissions?: string[]
    role?: string
    roles?: string[]
    requireAll?: boolean
    fallback?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    requireAll: false,
    fallback: false,
})

const { hasPermission, hasAnyPermission, hasAllPermissions, hasRole, hasAnyRole } = usePermissions()

const hasAccess = computed(() => {
    // If fallback is true, show content when user doesn't have access
    const shouldShow = (() => {
        // Check permissions
        if (props.permission) {
            return hasPermission(props.permission)
        }
        
        if (props.permissions && props.permissions.length > 0) {
            return props.requireAll 
                ? hasAllPermissions(props.permissions)
                : hasAnyPermission(props.permissions)
        }
        
        // Check roles
        if (props.role) {
            return hasRole(props.role)
        }
        
        if (props.roles && props.roles.length > 0) {
            return hasAnyRole(props.roles)
        }
        
        // If no conditions specified, allow access
        return true
    })()
    
    return props.fallback ? !shouldShow : shouldShow
})
</script> 