<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
// import { Badge } from '@/components/ui/badge'
import PermissionGuard from '@/components/PermissionGuard.vue'
import { usePermissions } from '@/composables/usePermissions'
import { Users, Shield, Wrench, BarChart3, Database, Settings } from 'lucide-vue-next'

interface Props {
    permissions: {
        canViewUsers: boolean
        canManageSystem: boolean
        canViewAnalytics: boolean
    }
}

defineProps<Props>()

const { user, isAdminLevel, isSuperAdmin } = usePermissions()
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Admin Dashboard</h1>
                    <p class="text-muted-foreground">
                        Welcome back, {{ user?.name }}
                        <span v-if="user?.role" class="ml-2 px-2 py-1 text-xs bg-secondary text-secondary-foreground rounded-md">
                            {{ user.role.display_name }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <PermissionGuard permission="user.view">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">--</div>
                            <p class="text-xs text-muted-foreground">
                                Active user accounts
                            </p>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <PermissionGuard permission="payslip.view_all">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Payslips</CardTitle>
                            <Database class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">--</div>
                            <p class="text-xs text-muted-foreground">
                                Processed documents
                            </p>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <PermissionGuard permission="analytics.view">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Success Rate</CardTitle>
                            <BarChart3 class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">--%</div>
                            <p class="text-xs text-muted-foreground">
                                Processing success rate
                            </p>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <PermissionGuard permission="system.view_health">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">System Health</CardTitle>
                            <Wrench class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-green-600">Healthy</div>
                            <p class="text-xs text-muted-foreground">
                                All systems operational
                            </p>
                        </CardContent>
                    </Card>
                </PermissionGuard>
            </div>

            <!-- Quick Actions -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- User Management -->
                <PermissionGuard permission="user.view">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-5 w-5" />
                                User Management
                            </CardTitle>
                            <CardDescription>
                                Manage user accounts, roles, and permissions
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="route('admin.users.index')">View All Users</a>
                                </Button>
                                <PermissionGuard permission="user.create">
                                    <Button size="sm" as-child>
                                        <a :href="route('admin.users.create')">Add New User</a>
                                    </Button>
                                </PermissionGuard>
                            </div>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <!-- System Management -->
                <PermissionGuard permission="system.view_health">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Wrench class="h-5 w-5" />
                                System Management
                            </CardTitle>
                            <CardDescription>
                                Monitor system health and perform maintenance
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="route('admin.system.index')">System Status</a>
                                </Button>
                                <PermissionGuard permission="system.clear_cache">
                                    <Button size="sm" variant="outline">
                                        Clear Cache
                                    </Button>
                                </PermissionGuard>
                            </div>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <!-- Role Management (Super Admin Only) -->
                <PermissionGuard role="super_admin">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="h-5 w-5" />
                                Roles & Permissions
                            </CardTitle>
                            <CardDescription>
                                Configure system roles and permissions
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="route('admin.roles.index')">Manage Roles</a>
                                </Button>
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="route('admin.permissions.index')">View Permissions</a>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </PermissionGuard>

                <!-- Analytics -->
                <PermissionGuard permission="analytics.view">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BarChart3 class="h-5 w-5" />
                                Analytics & Reports
                            </CardTitle>
                            <CardDescription>
                                View system analytics and generate reports
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex gap-2">
                                <Button size="sm" variant="outline" as-child>
                                    <a :href="route('analytics')">View Analytics</a>
                                </Button>
                                <PermissionGuard permission="report.generate">
                                    <Button size="sm" variant="outline">
                                        Generate Report
                                    </Button>
                                </PermissionGuard>
                            </div>
                        </CardContent>
                    </Card>
                </PermissionGuard>
            </div>

            <!-- Permission Debug (Development Only) -->
            <PermissionGuard role="super_admin">
                <Card>
                    <CardHeader>
                        <CardTitle>Permission Debug</CardTitle>
                        <CardDescription>Current user permissions (Super Admin only)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div><strong>Role:</strong> {{ user?.role?.display_name || 'No role assigned' }}</div>
                            <div><strong>Permissions:</strong></div>
                            <div class="flex flex-wrap gap-1">
                                <span 
                                    v-for="permission in user?.permissions" 
                                    :key="permission" 
                                    class="px-2 py-1 text-xs border border-border rounded-md bg-background"
                                >
                                    {{ permission }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>
        </div>
    </AppLayout>
</template> 