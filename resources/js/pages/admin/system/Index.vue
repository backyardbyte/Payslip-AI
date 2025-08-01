<template>
    <Head title="System Management" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">System Management</h1>
                <p class="text-muted-foreground">Monitor and manage system health and performance</p>
            </div>
        </div>

        <!-- System Health Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">System Status</p>
                        <p class="text-2xl font-bold text-green-600">Healthy</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                        <CheckCircle class="h-6 w-6 text-green-600" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Queue Status</p>
                        <p class="text-2xl font-bold">{{ queueStats.pending || 0 }}</p>
                        <p class="text-xs text-muted-foreground">Pending Jobs</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <Clock class="h-6 w-6 text-blue-600" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Storage Used</p>
                        <p class="text-2xl font-bold">{{ formatBytes(storageStats.used || 0) }}</p>
                        <p class="text-xs text-muted-foreground">of {{ formatBytes(storageStats.total || 0) }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-orange-100 flex items-center justify-center">
                        <HardDrive class="h-6 w-6 text-orange-600" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Active Users</p>
                        <p class="text-2xl font-bold">{{ userStats.active || 0 }}</p>
                        <p class="text-xs text-muted-foreground">Last 24h</p>
                    </div>
                    <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                        <Users class="h-6 w-6 text-purple-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- System Actions -->
        <div class="grid gap-6 md:grid-cols-2">
            <!-- Cache Management -->
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Cache Management</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Application Cache</p>
                            <p class="text-sm text-muted-foreground">Clear compiled views and cached data</p>
                        </div>
                        <button
                            v-if="permissions.canClearCache"
                            @click="clearCache('application')"
                            :disabled="isLoading.clearCache"
                            class="inline-flex items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Clear Cache
                        </button>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Route Cache</p>
                            <p class="text-sm text-muted-foreground">Clear and rebuild route cache</p>
                        </div>
                        <button
                            v-if="permissions.canClearCache"
                            @click="clearCache('routes')"
                            :disabled="isLoading.clearRoutes"
                            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                        >
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Clear Routes
                        </button>
                    </div>
                </div>
            </div>

            <!-- Database Management -->
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Database Management</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Optimize Database</p>
                            <p class="text-sm text-muted-foreground">Optimize tables and rebuild indexes</p>
                        </div>
                        <button
                            v-if="permissions.canOptimizeDatabase"
                            @click="optimizeDatabase()"
                            :disabled="isLoading.optimizeDb"
                            class="inline-flex items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Database class="mr-2 h-4 w-4" />
                            Optimize
                        </button>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Database Backup</p>
                            <p class="text-sm text-muted-foreground">Create database backup</p>
                        </div>
                        <button
                            @click="createBackup()"
                            :disabled="isLoading.backup"
                            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            Backup
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Cleanup -->
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">System Cleanup</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Temporary Files</p>
                            <p class="text-sm text-muted-foreground">Remove temporary and cache files</p>
                        </div>
                        <button
                            v-if="permissions.canCleanup"
                            @click="cleanup('temp')"
                            :disabled="isLoading.cleanupTemp"
                            class="inline-flex items-center justify-center rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Clean Temp
                        </button>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Log Files</p>
                            <p class="text-sm text-muted-foreground">Archive and clean old log files</p>
                        </div>
                        <button
                            v-if="permissions.canClearLogs"
                            @click="cleanup('logs')"
                            :disabled="isLoading.cleanupLogs"
                            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                        >
                            <FileText class="mr-2 h-4 w-4" />
                            Clean Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Queue Management -->
            <div class="rounded-lg border bg-card p-6 shadow-sm">
                <h2 class="text-lg font-semibold mb-4">Queue Management</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Queue Status</p>
                            <p class="text-sm text-muted-foreground">Monitor and manage job queues</p>
                        </div>
                        <button
                            @click="refreshQueueStats()"
                            :disabled="isLoading.queueStats"
                            class="inline-flex items-center justify-center rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                        >
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Refresh
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <div class="text-center p-2 bg-muted rounded">
                            <p class="font-medium">{{ queueStats.pending || 0 }}</p>
                            <p class="text-muted-foreground">Pending</p>
                        </div>
                        <div class="text-center p-2 bg-muted rounded">
                            <p class="font-medium">{{ queueStats.processing || 0 }}</p>
                            <p class="text-muted-foreground">Processing</p>
                        </div>
                        <div class="text-center p-2 bg-muted rounded">
                            <p class="font-medium">{{ queueStats.failed || 0 }}</p>
                            <p class="text-muted-foreground">Failed</p>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import { 
    CheckCircle, 
    Clock, 
    HardDrive, 
    Users, 
    Trash2, 
    RefreshCw, 
    Database, 
    Download, 
    FileText 
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'

interface Props {
    permissions: {
        canClearCache: boolean
        canOptimizeDatabase: boolean
        canCleanup: boolean
        canClearLogs: boolean
        canManageSettings: boolean
    }
}

const props = defineProps<Props>()

const isLoading = reactive({
    clearCache: false,
    clearRoutes: false,
    optimizeDb: false,
    backup: false,
    cleanupTemp: false,
    cleanupLogs: false,
    queueStats: false,
})

const queueStats = ref({
    pending: 0,
    processing: 0,
    failed: 0,
})

const storageStats = ref({
    used: 1024 * 1024 * 1024 * 2.5, // 2.5GB
    total: 1024 * 1024 * 1024 * 10, // 10GB
})

const userStats = ref({
    active: 12,
})

const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const clearCache = async (type: string) => {
    const loadingKey = type === 'application' ? 'clearCache' : 'clearRoutes'
    isLoading[loadingKey] = true
    
    try {
        const response = await fetch('/api/system/clear-cache', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ type })
        });

        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
        } else {
            throw new Error(data.message || 'Failed to clear cache');
        }
    } catch (error) {
        console.error('Error clearing cache:', error);
        alert('Error clearing cache: ' + (error instanceof Error ? error.message : 'Unknown error'));
    } finally {
        isLoading[loadingKey] = false
    }
}

const optimizeDatabase = async () => {
    isLoading.optimizeDb = true
    
    try {
        const response = await fetch('/api/system/optimize-database', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        const data = await response.json();
        
        if (data.success) {
            alert(`${data.message}\nOptimized ${data.total_tables} tables successfully!`);
        } else {
            throw new Error(data.message || 'Failed to optimize database');
        }
    } catch (error) {
        console.error('Error optimizing database:', error);
        alert('Error optimizing database: ' + (error instanceof Error ? error.message : 'Unknown error'));
    } finally {
        isLoading.optimizeDb = false
    }
}

const createBackup = async () => {
    isLoading.backup = true
    
    try {
        const response = await fetch('/api/system/create-backup', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        const data = await response.json();
        
        if (data.success) {
            alert(`${data.message}\nBackup file: ${data.backup_file}\nSize: ${data.file_size_mb} MB`);
        } else {
            throw new Error(data.message || 'Failed to create backup');
        }
    } catch (error) {
        console.error('Error creating backup:', error);
        alert('Error creating backup: ' + (error instanceof Error ? error.message : 'Unknown error'));
    } finally {
        isLoading.backup = false
    }
}

const cleanup = async (type: string) => {
    const loadingKey = type === 'temp' ? 'cleanupTemp' : 'cleanupLogs'
    isLoading[loadingKey] = true
    
    try {
        const response = await fetch('/api/system/cleanup', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ type, days: 30 })
        });

        const data = await response.json();
        
        if (data.success) {
            const results = data.results;
            let message = data.message;
            
            if (typeof results === 'object' && results.total_deleted_count !== undefined) {
                message += `\nDeleted ${results.total_deleted_count} items\nFreed ${results.total_freed_space_mb} MB`;
            } else if (results.deleted_count !== undefined) {
                message += `\nDeleted ${results.deleted_count} items\nFreed ${Math.round(results.freed_space / 1024 / 1024)} MB`;
            }
            
            alert(message);
        } else {
            throw new Error(data.message || `Failed to cleanup ${type}`);
        }
    } catch (error) {
        console.error(`Error cleaning up ${type}:`, error);
        alert(`Error cleaning up ${type}: ` + (error instanceof Error ? error.message : 'Unknown error'));
    } finally {
        isLoading[loadingKey] = false
    }
}

const refreshQueueStats = async () => {
    isLoading.queueStats = true
    
    try {
        const response = await fetch('/api/system/queue-stats', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        const data = await response.json();
        
        if (data.success) {
            queueStats.value = {
                pending: data.data.pending || 0,
                processing: data.data.processing || 0,
                failed: data.data.failed || 0,
            };
        } else {
            throw new Error(data.message || 'Failed to get queue stats');
        }
    } catch (error) {
        console.error('Error refreshing queue stats:', error);
        // Don't show alert for this as it's not critical and runs automatically
    } finally {
        isLoading.queueStats = false
    }
}
</script>

 