<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut, Home, History, Landmark, Settings, BarChart3, Users, Shield, Wrench } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PermissionGuard from '@/components/PermissionGuard.vue';
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const { isAdminLevel, canViewAnalytics, canViewUsers, canViewSystemHealth, canManageSettings } = usePermissions();
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-muted/40">
        <aside class="fixed inset-y-0 left-0 z-10 hidden w-60 flex-col border-r bg-background sm:flex">
            <div class="flex h-16 shrink-0 items-center border-b px-6">
                <Link :href="route('dashboard')">
                    <AppLogoIcon class="h-8 w-8 text-foreground" />
                </Link>
            </div>
            <nav class="flex flex-col gap-2 p-4 text-sm font-medium">
                <PermissionGuard permission="payslip.view">
                    <Link
                        :href="route('dashboard')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                        :class="{ 'bg-muted text-primary': page.url.startsWith('/dashboard') }"
                    >
                        <Home class="h-5 w-5" />
                        Dashboard
                    </Link>
                </PermissionGuard>
                <PermissionGuard permission="payslip.view">
                    <Link
                        :href="route('history')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                        :class="{ 'bg-muted text-primary': page.url.startsWith('/history') }"
                    >
                        <History class="h-5 w-5" />
                        History
                    </Link>
                </PermissionGuard>
                <Link
                    :href="route('koperasi')"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                    :class="{ 'bg-muted text-primary': page.url.startsWith('/koperasi') }"
                >
                    <Landmark class="h-5 w-5" />
                    Koperasi
                </Link>
                
                <!-- Analytics Section -->
                <PermissionGuard permission="analytics.view">
                    <div class="my-2 border-t border-border"></div>
                    <Link
                        :href="route('analytics')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                        :class="{ 'bg-muted text-primary': page.url.startsWith('/analytics') }"
                    >
                        <BarChart3 class="h-5 w-5" />
                        Analytics
                    </Link>
                </PermissionGuard>
                
                <!-- Admin Section -->
                <template v-if="canViewUsers || canViewSystemHealth || isAdminLevel">
                    <div class="my-2 border-t border-border"></div>
                    <div class="px-3 py-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Administration
                    </div>
                    
                    <PermissionGuard permission="user.view">
                        <Link
                            :href="route('admin.users.index')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                            :class="{ 'bg-muted text-primary': page.url.startsWith('/admin/users') }"
                        >
                            <Users class="h-5 w-5" />
                            User Management
                        </Link>
                    </PermissionGuard>
                    
                    <PermissionGuard :roles="['super_admin']">
                        <Link
                            :href="route('admin.roles.index')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                            :class="{ 'bg-muted text-primary': page.url.startsWith('/admin/roles') }"
                        >
                            <Shield class="h-5 w-5" />
                            Roles & Permissions
                        </Link>
                    </PermissionGuard>
                    
                    <PermissionGuard permission="system.view_health">
                        <Link
                            :href="route('admin.system.index')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                            :class="{ 'bg-muted text-primary': page.url.startsWith('/admin/system') }"
                        >
                            <Wrench class="h-5 w-5" />
                            System Management
                        </Link>
                    </PermissionGuard>
                </template>
                
                <!-- Settings Section -->
                <PermissionGuard permission="system.manage_settings">
                    <Link
                        :href="route('settings')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                        :class="{ 'bg-muted text-primary': page.url.startsWith('/settings') }"
                    >
                        <Settings class="h-5 w-5" />
                        Settings
                    </Link>
                </PermissionGuard>
            </nav>
            <div class="mt-auto p-4">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-muted-foreground transition-all hover:text-primary"
                >
                    <LogOut class="h-5 w-5" />
                    Logout
                </Link>
            </div>
        </aside>
        <div class="flex flex-col sm:gap-4 sm:py-4 sm:pl-64">
            <main class="flex-1 gap-4 p-4 sm:px-6 sm:py-0 md:gap-8">
                <slot />
            </main>
        </div>
    </div>
</template>
