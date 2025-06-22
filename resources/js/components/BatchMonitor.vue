<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Activity class="h-5 w-5" />
                        Batch Processing Monitor
                    </CardTitle>
                    <CardDescription>Track your batch processing operations in real-time</CardDescription>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="refreshBatches" :disabled="isRefreshing">
                        <RefreshCw :class="['w-4 h-4', isRefreshing && 'animate-spin']" />
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Filter class="w-4 h-4 mr-2" />
                                Filter
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem @click="statusFilter = null">All Batches</DropdownMenuItem>
                            <DropdownMenuItem @click="statusFilter = 'pending'">Pending</DropdownMenuItem>
                            <DropdownMenuItem @click="statusFilter = 'processing'">Processing</DropdownMenuItem>
                            <DropdownMenuItem @click="statusFilter = 'completed'">Completed</DropdownMenuItem>
                            <DropdownMenuItem @click="statusFilter = 'failed'">Failed</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center p-8">
                <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
                <span class="ml-2 text-muted-foreground">Loading batches...</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredBatches.length === 0" class="flex flex-col items-center justify-center p-8 text-center">
                <Package class="h-12 w-12 text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium">No batch operations found</h3>
                <p class="text-muted-foreground mt-1">
                    {{ statusFilter ? `No ${statusFilter} batches found.` : 'Start by uploading files in batch mode.' }}
                </p>
            </div>

            <!-- Batch List -->
            <div v-else class="space-y-4">
                <div
                    v-for="batch in filteredBatches"
                    :key="batch.batch_id"
                    class="border rounded-lg p-4 space-y-3"
                >
                    <!-- Batch Header -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ batch.name }}</h3>
                                <Badge :variant="getBatchStatusVariant(batch.status)">
                                    {{ batch.status }}
                                </Badge>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="viewBatchDetails(batch)"
                            >
                                <Eye class="w-4 h-4 mr-2" />
                                View
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <MoreHorizontal class="w-4 h-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem @click="viewBatchDetails(batch)">
                                        <Eye class="w-4 h-4 mr-2" />
                                        View Details
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="batch.status === 'processing' || batch.status === 'pending'"
                                        @click="cancelBatch(batch)"
                                    >
                                        <XCircle class="w-4 h-4 mr-2" />
                                        Cancel
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="batch.status === 'completed' || batch.status === 'failed'"
                                        @click="deleteBatch(batch)"
                                        class="text-red-600"
                                    >
                                        <Trash2 class="w-4 h-4 mr-2" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="batch.status === 'processing' || batch.status === 'pending'" class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span>Progress: {{ batch.processed_files }}/{{ batch.total_files }} files</span>
                            <span>{{ batch.progress_percentage }}%</span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-2">
                            <div
                                class="bg-primary h-2 rounded-full transition-all duration-300"
                                :style="{ width: `${batch.progress_percentage}%` }"
                            ></div>
                        </div>
                        <div v-if="batch.estimated_completion" class="text-xs text-muted-foreground">
                            Estimated completion: {{ formatDateTime(batch.estimated_completion) }}
                        </div>
                    </div>

                    <!-- Batch Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-muted-foreground" />
                            <span>{{ batch.total_files }} files</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="w-4 h-4 text-green-500" />
                            <span>{{ batch.successful_files }} successful</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <XCircle class="w-4 h-4 text-red-500" />
                            <span>{{ batch.failed_files }} failed</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock class="w-4 h-4 text-muted-foreground" />
                            <span>{{ formatRelativeTime(batch.created_at) }}</span>
                        </div>
                    </div>

                    <!-- File Preview -->
                    <div v-if="batch.payslips_preview && batch.payslips_preview.length > 0" class="space-y-2">
                        <div class="text-sm font-medium">Files:</div>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="file in batch.payslips_preview"
                                :key="file.id"
                                :variant="getFileStatusVariant(file.status)"
                                class="text-xs"
                            >
                                {{ file.name }}
                            </Badge>
                            <Badge v-if="batch.total_files > batch.payslips_preview.length" variant="outline" class="text-xs">
                                +{{ batch.total_files - batch.payslips_preview.length }} more
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Batch Details Dialog -->
    <Dialog v-model:open="showDetailsDialog">
        <DialogContent class="max-w-4xl max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Batch Details: {{ selectedBatch?.name }}</DialogTitle>
            </DialogHeader>
            <div v-if="selectedBatch" class="space-y-6">
                <!-- Batch Overview -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="space-y-1">
                        <div class="text-sm font-medium">Status</div>
                        <Badge :variant="getBatchStatusVariant(selectedBatch.status)">
                            {{ selectedBatch.status }}
                        </Badge>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm font-medium">Progress</div>
                        <div class="text-sm">{{ selectedBatch.progress_percentage }}%</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm font-medium">Success Rate</div>
                        <div class="text-sm">{{ selectedBatch.success_rate }}%</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm font-medium">Created</div>
                        <div class="text-sm">{{ formatDateTime(selectedBatch.created_at) }}</div>
                    </div>
                </div>

                <!-- Files List -->
                <div v-if="batchDetails?.payslips" class="space-y-4">
                    <h3 class="text-lg font-semibold">Files ({{ batchDetails.payslips.length }})</h3>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <div
                            v-for="file in batchDetails.payslips"
                            :key="file.id"
                            class="flex items-center justify-between p-3 border rounded-lg"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="w-4 h-4 text-muted-foreground" />
                                <div>
                                    <div class="font-medium">{{ file.name }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ (file.size / 1024).toFixed(1) }} KB
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge :variant="getFileStatusVariant(file.status)">
                                    {{ file.status }}
                                </Badge>
                                <div v-if="file.processing_started_at && file.processing_completed_at" class="text-xs text-muted-foreground">
                                    {{ calculateProcessingTime(file.processing_started_at, file.processing_completed_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import {
    Activity, RefreshCw, Filter, LoaderCircle, Package, Eye, MoreHorizontal,
    XCircle, Trash2, FileText, CheckCircle, Clock
} from 'lucide-vue-next'

interface BatchOperation {
    id: number
    batch_id: string
    name: string
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled'
    total_files: number
    processed_files: number
    successful_files: number
    failed_files: number
    progress_percentage: number
    success_rate: number
    estimated_completion?: string
    started_at?: string
    completed_at?: string
    created_at: string
    payslips_preview?: Array<{
        id: number
        name: string
        status: string
    }>
}

interface BatchDetails extends BatchOperation {
    payslips: Array<{
        id: number
        name: string
        status: string
        size: number
        processing_started_at?: string
        processing_completed_at?: string
        error?: string
    }>
}

const batches = ref<BatchOperation[]>([])
const isLoading = ref(false)
const isRefreshing = ref(false)
const statusFilter = ref<string | null>(null)
const showDetailsDialog = ref(false)
const selectedBatch = ref<BatchOperation | null>(null)
const batchDetails = ref<BatchDetails | null>(null)

let pollingInterval: number | undefined

const filteredBatches = computed(() => {
    if (!statusFilter.value) return batches.value
    return batches.value.filter(batch => batch.status === statusFilter.value)
})

const fetchBatches = async () => {
    try {
        const response = await fetch('/api/batch/', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                }
            })
        if (response.ok) {
            const data = await response.json()
            batches.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to fetch batches:', error)
    }
}

const refreshBatches = async () => {
    isRefreshing.value = true
    await fetchBatches()
    isRefreshing.value = false
}

const viewBatchDetails = async (batch: BatchOperation) => {
    selectedBatch.value = batch
    showDetailsDialog.value = true
    
    try {
        const response = await fetch(`/api/batch/${batch.id}`)
        if (response.ok) {
            const data = await response.json()
            batchDetails.value = data.data
        }
    } catch (error) {
        console.error('Failed to fetch batch details:', error)
    }
}

const cancelBatch = async (batch: BatchOperation) => {
    if (!confirm(`Are you sure you want to cancel the batch "${batch.name}"?`)) {
        return
    }

    try {
        const response = await fetch(`/api/batch/${batch.id}/cancel`, {
            method: 'POST'
        })
        
        if (response.ok) {
            await refreshBatches()
        }
    } catch (error) {
        console.error('Failed to cancel batch:', error)
    }
}

const deleteBatch = async (batch: BatchOperation) => {
    if (!confirm(`Are you sure you want to delete the batch "${batch.name}"? This will also delete all associated files.`)) {
        return
    }

    try {
        const response = await fetch(`/api/batch/${batch.id}`, {
            method: 'DELETE'
        })
        
        if (response.ok) {
            await refreshBatches()
        }
    } catch (error) {
        console.error('Failed to delete batch:', error)
    }
}

const getBatchStatusVariant = (status: string) => {
    switch (status) {
        case 'completed': return 'default'
        case 'processing': return 'secondary'
        case 'failed': return 'destructive'
        case 'cancelled': return 'outline'
        default: return 'secondary'
    }
}

const getFileStatusVariant = (status: string) => {
    switch (status) {
        case 'completed': return 'default'
        case 'processing': return 'secondary'
        case 'failed': return 'destructive'
        default: return 'outline'
    }
}

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString()
}

const formatRelativeTime = (dateString: string) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMins / 60)
    const diffDays = Math.floor(diffHours / 24)

    if (diffMins < 1) return 'Just now'
    if (diffMins < 60) return `${diffMins}m ago`
    if (diffHours < 24) return `${diffHours}h ago`
    return `${diffDays}d ago`
}

const calculateProcessingTime = (startTime: string, endTime: string) => {
    const start = new Date(startTime)
    const end = new Date(endTime)
    const diffMs = end.getTime() - start.getTime()
    const diffSecs = Math.floor(diffMs / 1000)
    
    if (diffSecs < 60) return `${diffSecs}s`
    const diffMins = Math.floor(diffSecs / 60)
    return `${diffMins}m ${diffSecs % 60}s`
}

onMounted(async () => {
    isLoading.value = true
    await fetchBatches()
    isLoading.value = false

    // Set up polling for active batches
    pollingInterval = setInterval(async () => {
        const hasActiveBatches = batches.value.some(batch => 
            batch.status === 'pending' || batch.status === 'processing'
        )
        
        if (hasActiveBatches) {
            await fetchBatches()
        }
    }, 5000) // Poll every 5 seconds
})

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval)
    }
})
</script> 