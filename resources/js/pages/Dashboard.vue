<template>
    <Head title="Dashboard" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-3 p-4 sm:p-6">
            <!-- Statistics Cards -->
            <PermissionGuard permission="payslip.view">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                    <Card class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-xs">Total</p>
                                    <p class="text-xl font-bold">{{ statistics?.stats?.total || 0 }}</p>
                                </div>
                                <FileText class="h-6 w-6 text-blue-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-yellow-100 text-xs">Queued</p>
                                    <p class="text-xl font-bold">{{ statistics?.stats?.queued || 0 }}</p>
                                </div>
                                <Clock class="h-6 w-6 text-yellow-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-orange-500 to-orange-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-orange-100 text-xs">Processing</p>
                                    <p class="text-xl font-bold">{{ statistics?.stats?.processing || 0 }}</p>
                                </div>
                                <LoaderCircle class="h-6 w-6 text-orange-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-green-500 to-green-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-xs">Completed</p>
                                    <p class="text-xl font-bold">{{ statistics?.stats?.completed || 0 }}</p>
                                </div>
                                <CheckCircle class="h-6 w-6 text-green-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-red-500 to-red-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-red-100 text-xs">Failed</p>
                                    <p class="text-xl font-bold">{{ statistics?.stats?.failed || 0 }}</p>
                                </div>
                                <XCircle class="h-6 w-6 text-red-200" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </PermissionGuard>

            <!-- Upload Section -->
            <PermissionGuard permission="payslip.create">
                <!-- Upload Mode Toggle -->
                <div class="flex items-center justify-center">
                    <div class="flex items-center space-x-1 bg-muted p-1 rounded-lg">
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="[
                                'px-3 py-1.5 rounded-md transition-colors h-7 text-xs',
                                uploadMode === 'single' ? 'bg-background shadow-sm' : 'hover:bg-background/50'
                            ]"
                            @click="uploadMode = 'single'"
                        >
                            <UploadCloud class="w-3 h-3 mr-1.5" />
                            Single Upload
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="[
                                'px-3 py-1.5 rounded-md transition-colors h-7 text-xs',
                                uploadMode === 'batch' ? 'bg-background shadow-sm' : 'hover:bg-background/50'
                            ]"
                            @click="uploadMode = 'batch'"
                        >
                            <Package class="w-3 h-3 mr-1.5" />
                            Batch Upload
                        </Button>
                    </div>
                </div>

                <!-- Single Upload Mode -->
                <EnhancedFileUploader 
                    v-if="uploadMode === 'single'"
                    :max-file-size="10"
                    :allowed-file-types="['pdf', 'png', 'jpg', 'jpeg']"
                    :multiple="true"
                    @upload-complete="onUploadComplete"
                    @file-added="onFileAdded"
                    @file-removed="onFileRemoved"
                />

                <!-- Batch Upload Mode -->
                <BatchUploader v-else-if="uploadMode === 'batch'" @batch-uploaded="onBatchUploaded" />
            </PermissionGuard>

            <!-- Batch Monitor -->
            <PermissionGuard permission="payslip.view">
                <BatchMonitor v-if="uploadMode === 'batch'" />
            </PermissionGuard>

            <!-- Processing Queue -->
            <PermissionGuard permission="queue.view">
                <Card v-if="queuedFiles.length > 0" class="bg-gradient-to-br from-slate-50 to-gray-50 border-0 shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="flex items-center gap-2 text-base font-semibold text-gray-900">
                                    <div class="p-1.5 bg-blue-100 rounded-lg">
                                        <List class="h-4 w-4 text-blue-600" />
                                    </div>
                                    Processing Queue
                                </CardTitle>
                                <CardDescription class="text-sm text-gray-600 mt-1">AI is processing your uploaded payslips</CardDescription>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="refreshQueue" :disabled="isRefreshing" class="h-8 px-3 text-xs border-gray-200 hover:bg-gray-50">
                                    <RefreshCw :class="['h-3 w-3 mr-1.5', isRefreshing && 'animate-spin']" />
                                    Refresh
                                </Button>
                                <PermissionGuard permission="queue.manage">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="outline" size="sm" class="h-8 px-3 text-xs border-gray-200 hover:bg-gray-50">
                                                <Settings class="h-3 w-3 mr-1.5" />
                                                Manage
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="clearCompleted" :disabled="isClearing">
                                                <CheckCircle class="h-3 w-3 mr-2" />
                                                Clear Recent Items
                                            </DropdownMenuItem>
                                            <PermissionGuard permission="queue.clear">
                                                <DropdownMenuItem @click="clearAll" :disabled="isClearing" class="text-red-600">
                                                    <Trash2 class="h-3 w-3 mr-2" />
                                                    Clear Queue
                                                </DropdownMenuItem>
                                            </PermissionGuard>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </PermissionGuard>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Collapsible v-for="file in queuedFiles" :key="file.job_id" class="group">
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-between p-4">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <FileText class="h-4 w-4 text-gray-600" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="font-medium text-gray-900 truncate text-sm">{{ file.name }}</h3>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-xs text-gray-500">{{ (file.size / 1024).toFixed(1) }} KB</span>
                                                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                                <span class="text-xs text-gray-500" v-if="file.created_at">{{ new Date(file.created_at).toLocaleDateString() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span :class="[
                                            'px-2.5 py-1 text-xs font-medium rounded-full',
                                            file.status === 'completed' ? 'bg-green-100 text-green-700' :
                                            file.status === 'failed' ? 'bg-red-100 text-red-700' :
                                            file.status === 'processing' ? 'bg-blue-100 text-blue-700' :
                                            'bg-gray-100 text-gray-700'
                                        ]">
                                            {{ file.status.charAt(0).toUpperCase() + file.status.slice(1) }}
                                        </span>
                                        <div class="flex gap-1">
                                            <CollapsibleTrigger asChild>
                                                <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-gray-600 hover:bg-gray-50">
                                                    <ChevronDown class="h-4 w-4" />
                                                </Button>
                                            </CollapsibleTrigger>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-red-600 hover:bg-red-50" @click="deletePayslip(file.id)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                
                                <CollapsibleContent>
                                    <div class="border-t border-gray-50 bg-gray-50/50">
                                        <!-- Error State -->
                                        <div v-if="file.status === 'failed' && file.data?.error" class="p-4">
                                            <div class="flex items-start gap-3 p-3 bg-red-50 border border-red-100 rounded-lg">
                                                <XCircle class="h-5 w-5 text-red-500 mt-0.5" />
                                                <div>
                                                    <h4 class="font-medium text-red-900 text-sm">Processing Failed</h4>
                                                    <p class="text-red-700 text-sm mt-1">{{ file.data.error }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Success State -->
                                        <div v-else-if="file.status === 'completed' && file.data" class="p-4 space-y-4">
                                            <!-- Extracted Data -->
                                            <div class="bg-white rounded-lg p-4 border border-gray-100">
                                                <h4 class="font-semibold text-gray-900 text-sm mb-3 flex items-center gap-2">
                                                    <CheckCircle class="h-4 w-4 text-green-500" />
                                                    Extracted Information
                                                </h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div v-if="file.data.nama" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Employee Name</span>
                                                        <span class="font-medium text-gray-900 text-sm">{{ file.data.nama }}</span>
                                                    </div>
                                                    <div v-if="file.data.no_gaji" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Employee ID</span>
                                                        <span class="font-mono text-gray-900 text-sm">{{ file.data.no_gaji }}</span>
                                                    </div>
                                                    <div v-if="file.data.bulan" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Period</span>
                                                        <span class="font-mono text-gray-900 text-sm">{{ file.data.bulan }}</span>
                                                    </div>
                                                    <div v-if="file.data.gaji_pokok" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Basic Salary</span>
                                                        <span class="font-mono font-medium text-gray-900 text-sm">RM {{ file.data.gaji_pokok?.toLocaleString() }}</span>
                                                    </div>
                                                    <div v-if="file.data.jumlah_pendapatan" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Total Income</span>
                                                        <span class="font-mono font-medium text-green-600 text-sm">RM {{ file.data.jumlah_pendapatan?.toLocaleString() }}</span>
                                                    </div>
                                                    <div v-if="file.data.jumlah_potongan" class="flex justify-between items-center py-2 border-b border-gray-50">
                                                        <span class="text-gray-600 text-sm">Total Deductions</span>
                                                        <span class="font-mono font-medium text-red-600 text-sm">RM {{ file.data.jumlah_potongan?.toLocaleString() }}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Net Salary Highlight -->
                                                <div v-if="file.data.gaji_bersih" class="mt-4 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-100">
                                                    <div class="flex justify-between items-center">
                                                        <span class="font-medium text-blue-900">Net Salary</span>
                                                        <span class="font-bold text-lg text-blue-900">RM {{ file.data.gaji_bersih?.toLocaleString() }}</span>
                                                    </div>
                                                    <div class="flex justify-between items-center mt-1">
                                                        <span class="text-blue-700 text-sm">Salary Percentage</span>
                                                        <span class="font-bold text-blue-700">{{ file.data.peratus_gaji_bersih ?? 'N/A' }}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Koperasi Eligibility -->
                                            <div class="bg-white rounded-lg p-4 border border-gray-100">
                                                <h4 class="font-semibold text-gray-900 text-sm mb-3 flex items-center gap-2">
                                                    <div class="p-1 bg-purple-100 rounded">
                                                        <CheckCircle class="h-3 w-3 text-purple-600" />
                                                    </div>
                                                    Koperasi Eligibility Check
                                                </h4>
                                                <div v-if="!file.data.koperasi_results || Object.keys(file.data.koperasi_results).length === 0" class="text-center py-4 text-gray-500">
                                                    <div class="p-2 bg-gray-100 rounded-lg inline-block mb-2">
                                                        <XCircle class="h-5 w-5 text-gray-400" />
                                                    </div>
                                                    <p class="text-sm">No active koperasi available for eligibility check</p>
                                                </div>
                                                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                                    <div v-for="(isEligible, name) in file.data.koperasi_results" :key="name" 
                                                         :class="[
                                                             'flex items-center justify-between p-2 rounded-lg border text-xs',
                                                             isEligible 
                                                                 ? 'bg-green-50 border-green-200 text-green-800' 
                                                                 : 'bg-red-50 border-red-200 text-red-800'
                                                         ]">
                                                        <span class="font-medium truncate mr-2">{{ name.replace('Koperasi ', '') }}</span>
                                                        <span :class="[
                                                            'flex-shrink-0 w-2 h-2 rounded-full',
                                                            isEligible ? 'bg-green-500' : 'bg-red-500'
                                                        ]"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Debug Info (Collapsible) -->
                                            <div v-if="file.data.debug_info" class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                                <details class="group">
                                                    <summary class="cursor-pointer text-gray-600 hover:text-gray-900 text-sm font-medium flex items-center gap-2">
                                                        <ChevronDown class="h-3 w-3 transition-transform group-open:rotate-180" />
                                                        Debug Information
                                                    </summary>
                                                    <div class="mt-2 p-2 bg-gray-100 rounded text-xs font-mono text-gray-700 space-y-1">
                                                        <div>Text Length: {{ file.data.debug_info.text_length }}</div>
                                                        <div>Patterns: {{ file.data.debug_info.extraction_patterns_found?.join(', ') || 'None' }}</div>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>
                    </CardContent>
                </Card>
            </PermissionGuard>

            <!-- No Access Message for Users without basic permissions -->
            <PermissionGuard permission="payslip.view" fallback>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="p-2 bg-muted rounded-full">
                                <Shield class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">Access Restricted</h3>
                                <p class="text-muted-foreground text-xs">You don't have permission to view payslip data.</p>
                                <p class="text-xs text-muted-foreground mt-1">Contact your administrator for access.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import PermissionGuard from '@/components/PermissionGuard.vue'
import BatchUploader from '@/components/BatchUploader.vue'
import BatchMonitor from '@/components/BatchMonitor.vue'
import EnhancedFileUploader from '@/components/EnhancedFileUploader.vue'
import { usePermissions } from '@/composables/usePermissions'
import { 
    UploadCloud, FileText, LoaderCircle, XCircle, ChevronDown, 
    Clock, CheckCircle, List, RefreshCw, Settings, Shield, Package, Trash2
} from 'lucide-vue-next'
import { ref, onMounted, onUnmounted } from 'vue'

interface QueuedFile {
    id: number;
    name: string;
    size: number;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    job_id: string;
    created_at?: string;
    data?: {
        nama?: string;
        no_gaji?: string;
        bulan?: string;
        gaji_pokok?: number;
        jumlah_pendapatan?: number;
        jumlah_potongan?: number;
        gaji_bersih?: number;
        peratus_gaji_bersih?: number;
        koperasi_results?: Record<string, boolean>;
        error?: string;
        debug_info?: {
            text_length: number;
            extraction_patterns_found: string[];
        };
    };
}

interface Statistics {
    stats: {
        total: number;
        queued: number;
        processing: number;
        completed: number;
        failed: number;
    };
    recent_activity: Array<{
        id: number;
        name: string;
        status: string;
        created_at: string;
    }>;
}

const queuedFiles = ref<QueuedFile[]>([])
const statistics = ref<Statistics | null>(null)
const isRefreshing = ref(false)
const isClearing = ref(false)
const uploadMode = ref<'single' | 'batch'>('single')
let pollingInterval: number | undefined

const { 
    canViewQueue, 
    canManageQueue, 
    canClearQueue, 
    canCreatePayslips,
    canViewPayslips 
} = usePermissions()

// Event handlers for EnhancedFileUploader
const onUploadComplete = async (results: any[]) => {
    // Process successful uploads and add to queue
    const successfulUploads = results.filter(result => !result.error)
    
    if (successfulUploads.length > 0) {
        const newQueuedFiles: QueuedFile[] = successfulUploads.map(result => ({
            id: result.id || Math.floor(Math.random() * 1000000),
            name: result.file?.name || 'Unknown file',
            size: result.file?.size || 0,
                status: 'queued',
            job_id: result.job_id || `job_${Math.random().toString(36).substr(2, 9)}`,
                created_at: new Date().toISOString(),
        }))

        queuedFiles.value.unshift(...newQueuedFiles)
        
        // Refresh statistics
        await fetchStatistics()
    }
}

const onFileAdded = (file: File) => {
    // Optional: Handle individual file additions if needed
    console.log('File added:', file.name)
}

const onFileRemoved = (fileId: string) => {
    // Optional: Handle individual file removals if needed
    console.log('File removed:', fileId)
}

const statusClass = (status: QueuedFile['status']) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400';
        case 'failed': return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400';
        case 'processing': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400 animate-pulse';
        case 'queued':
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-400';
    }
};

const fetchQueuedFiles = async () => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch('/api/queue', {
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Content-Type': 'application/json',
            }
        });
        if (response.ok) {
            queuedFiles.value = await response.json();
        }
    } catch (e) {
        // Handle error silently or show user notification
    }
};

const fetchStatistics = async () => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch('/api/payslips/statistics', {
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Content-Type': 'application/json',
            }
        });
        if (response.ok) {
            statistics.value = await response.json();
        }
    } catch (e) {
        // Handle error silently or show user notification
    }
};

const refreshQueue = async () => {
    isRefreshing.value = true;
    await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    isRefreshing.value = false;
};

const deletePayslip = async (id: number) => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/api/payslips/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            queuedFiles.value = queuedFiles.value.filter(f => f.id !== id);
            await fetchStatistics();
        }
    } catch (e) {
        // Handle error silently or show user notification
    }
};

const clearCompleted = async () => {
    isClearing.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch('/api/queue/clear', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            await refreshQueue();
        }
    } catch (e) {
        console.error("Could not clear queue", e);
    }
    isClearing.value = false;
};

const clearAll = async () => {
    if (!confirm('Are you sure you want to clear the processing queue? This will not affect your permanent history.')) {
        return;
    }
    
    isClearing.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch('/api/queue/clear', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            queuedFiles.value = [];
            await fetchStatistics();
        }
    } catch (e) {
        console.error("Could not clear queue", e);
    }
    isClearing.value = false;
};

const onBatchUploaded = async (result: any) => {
    // Refresh the queue and statistics after batch upload
    await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
};

onMounted(async () => {
    await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    
    // Poll for updates every 3 seconds
    pollingInterval = setInterval(async () => {
        await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    }, 3000);
});

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});
</script>
