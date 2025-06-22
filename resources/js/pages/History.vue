<template>
    <Head title="History" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Processing History</h1>
                    <p class="text-muted-foreground">View and analyze past payslip processing results</p>
                </div>
                <div class="flex gap-2">
                    <PermissionGuard permission="analytics.export">
                        <Button variant="outline" size="sm" @click="exportData" :disabled="isExporting">
                            <Download :class="['h-4 w-4 mr-2', isExporting && 'animate-pulse']" />
                            {{ isExporting ? 'Exporting...' : 'Export CSV' }}
                        </Button>
                    </PermissionGuard>
                    <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                        <RefreshCw :class="['h-4 w-4 mr-2', isLoading && 'animate-spin']" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <PermissionGuard permission="analytics.view">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent>
                            <div class="flex items-center space-x-2">
                                <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/50">
                                    <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Processed</p>
                                    <p class="text-2xl font-bold">{{ analytics.totalProcessed }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <div class="flex items-center space-x-2">
                                <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/50">
                                    <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Success Rate</p>
                                    <p class="text-2xl font-bold">{{ analytics.successRate }}%</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <div class="flex items-center space-x-2">
                                <div class="p-2 bg-orange-100 rounded-lg dark:bg-orange-900/50">
                                    <TrendingUp class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Avg. Salary</p>
                                    <p class="text-2xl font-bold">RM {{ analytics.avgSalary.toLocaleString() }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <div class="flex items-center space-x-2">
                                <div class="p-2 bg-purple-100 rounded-lg dark:bg-purple-900/50">
                                    <Users class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">Eligible Count</p>
                                    <p class="text-2xl font-bold">{{ analytics.eligibleCount }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </PermissionGuard>

            <!-- Filters -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    placeholder="Search by name or employee ID..."
                                    class="w-full pl-10 pr-4 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <select v-model="filters.status" class="px-3 py-2 border border-input rounded-md bg-background text-sm">
                                <option value="">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                            <select v-model="filters.period" class="px-3 py-2 border border-input rounded-md bg-background text-sm">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="quarter">This Quarter</option>
                            </select>
                            <Button variant="outline" size="sm" @click="clearFilters">
                                <X class="h-4 w-4 mr-2" />
                                Clear
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- History Table -->
            <Card class="flex-1">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Processing History</CardTitle>
                            <CardDescription>
                                {{ filteredHistory.length }} records found
                                <PermissionGuard permission="payslip.view_all" fallback>
                                    <span class="text-muted-foreground"> (showing only your records)</span>
                                </PermissionGuard>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="isLoading" class="flex items-center justify-center p-8">
                        <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
                        <span class="ml-2 text-muted-foreground">Loading history...</span>
                    </div>
                    <div v-else-if="filteredHistory.length === 0" class="flex flex-col items-center justify-center p-8 text-center">
                        <FileText class="h-12 w-12 text-muted-foreground mb-4" />
                        <h3 class="text-lg font-medium">No processing history found</h3>
                        <p class="text-muted-foreground mt-1">Start by uploading some payslips to see processing history here.</p>
                    </div>
                    <div v-else class="overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent border-b-0">
                                    <TableHead class="w-[200px]">Employee</TableHead>
                                    <TableHead class="w-[120px]">Month</TableHead>
                                    <TableHead class="w-[120px]">Gaji Bersih</TableHead>
                                    <TableHead class="w-[100px]">% Rate</TableHead>
                                    <TableHead class="w-[120px]">Eligibility</TableHead>
                                    <TableHead class="w-[100px]">Status</TableHead>
                                    <TableHead class="w-[130px]">Processed At</TableHead>
                                    <TableHead class="w-[80px] text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="record in paginatedHistory" :key="record.id" class="group">
                                    <TableCell>
                                        <div class="flex items-center space-x-3">
                                            <Avatar class="h-8 w-8">
                                                <AvatarFallback class="text-xs">
                                                    {{ getInitials(record.data?.nama || 'Unknown') }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <div class="font-medium">{{ record.data?.nama || 'Unknown' }}</div>
                                                <div class="text-xs text-muted-foreground">{{ record.data?.no_gaji || 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">{{ record.data?.bulan || 'N/A' }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-mono text-sm">
                                            {{ record.data?.gaji_bersih ? `RM ${record.data.gaji_bersih.toLocaleString()}` : 'N/A' }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="font-bold text-primary">
                                            {{ record.data?.peratus_gaji_bersih ? `${record.data.peratus_gaji_bersih}%` : 'N/A' }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <span 
                                                v-for="(isEligible, koperasi) in record.data?.koperasi_results" 
                                                :key="koperasi"
                                                :class="['px-1.5 py-0.5 text-xs rounded', isEligible ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']"
                                            >
                                                {{ isEligible ? '✓' : '✗' }}
                                            </span>
                                            <span v-if="!record.data?.koperasi_results" class="text-xs text-muted-foreground">N/A</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusClass(record.status)]">
                                            {{ record.status.charAt(0).toUpperCase() + record.status.slice(1) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">{{ formatDate(record.created_at) }}</div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="viewDetails(record)">
                                                    <Eye class="h-4 w-4 mr-2" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <PermissionGuard permission="payslip.update">
                                                    <DropdownMenuItem @click="reprocess(record)">
                                                        <RefreshCw class="h-4 w-4 mr-2" />
                                                        Reprocess
                                                    </DropdownMenuItem>
                                                </PermissionGuard>
                                                <PermissionGuard permission="analytics.export">
                                                    <DropdownMenuItem @click="exportRecord(record)">
                                                        <Download class="h-4 w-4 mr-2" />
                                                        Export
                                                    </DropdownMenuItem>
                                                </PermissionGuard>
                                                <PermissionGuard permission="payslip.view_all">
                                                    <DropdownMenuItem 
                                                        @click="deleteRecord(record)"
                                                        class="text-red-600 focus:text-red-600"
                                                    >
                                                        <Trash2 class="h-4 w-4 mr-2" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </PermissionGuard>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div class="flex items-center justify-between px-2 py-4 border-t">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ ((currentPage - 1) * pageSize) + 1 }} to {{ Math.min(currentPage * pageSize, filteredHistory.length) }} of {{ filteredHistory.length }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    @click="currentPage--" 
                                    :disabled="currentPage === 1"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                    Previous
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    @click="currentPage++" 
                                    :disabled="currentPage >= totalPages"
                                >
                                    Next
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Details Modal -->
            <Dialog v-model:open="isDetailsOpen">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Processing Details</DialogTitle>
                    </DialogHeader>
                    <div v-if="selectedRecord" class="space-y-6">
                        <!-- Employee Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Name</label>
                                <p class="text-sm font-medium">{{ selectedRecord.data?.nama || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Employee ID</label>
                                <p class="text-sm font-mono">{{ selectedRecord.data?.no_gaji || 'N/A' }}</p>
                            </div>
                        </div>

                        <!-- Financial Details -->
                        <div class="space-y-3">
                            <h4 class="font-medium">Financial Information</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Gaji Pokok:</span>
                                    <span class="font-mono">{{ selectedRecord.data?.gaji_pokok ? `RM ${selectedRecord.data.gaji_pokok.toLocaleString()}` : 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Pendapatan:</span>
                                    <span class="font-mono">{{ selectedRecord.data?.jumlah_pendapatan ? `RM ${selectedRecord.data.jumlah_pendapatan.toLocaleString()}` : 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Total Potongan:</span>
                                    <span class="font-mono">{{ selectedRecord.data?.jumlah_potongan ? `RM ${selectedRecord.data.jumlah_potongan.toLocaleString()}` : 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Gaji Bersih:</span>
                                    <span class="font-mono font-semibold">{{ selectedRecord.data?.gaji_bersih ? `RM ${selectedRecord.data.gaji_bersih.toLocaleString()}` : 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="p-3 bg-muted rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium">% Peratus Gaji Bersih:</span>
                                    <span class="text-xl font-bold text-primary">{{ selectedRecord.data?.peratus_gaji_bersih || 'N/A' }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Eligibility Results -->
                        <div v-if="selectedRecord.data?.koperasi_results" class="space-y-3">
                            <h4 class="font-medium">Koperasi Eligibility</h4>
                            <div class="grid gap-2">
                                <div 
                                    v-for="(isEligible, koperasi) in selectedRecord.data.koperasi_results" 
                                    :key="koperasi"
                                    class="flex items-center justify-between p-2 border rounded"
                                >
                                    <span class="font-medium">{{ koperasi }}</span>
                                    <span :class="['px-2 py-1 text-xs font-semibold rounded-full', isEligible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                        {{ isEligible ? 'Eligible' : 'Not Eligible' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="isDeleteConfirmOpen">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2 text-red-600">
                            <Trash2 class="h-5 w-5" />
                            Delete Payslip Record
                        </DialogTitle>
                    </DialogHeader>
                    <div v-if="recordToDelete" class="space-y-4">
                        <div class="text-sm text-muted-foreground">
                            Are you sure you want to delete this payslip record?
                        </div>
                        
                        <!-- Record Info -->
                        <div class="p-3 border rounded-lg bg-muted/50">
                            <div class="flex items-center space-x-3">
                                <Avatar class="h-8 w-8">
                                    <AvatarFallback class="text-xs bg-red-100 text-red-600">
                                        {{ getInitials(recordToDelete.data?.nama || 'Unknown') }}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div class="font-medium text-sm">{{ recordToDelete.data?.nama || 'Unknown' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ recordToDelete.data?.no_gaji || 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-sm text-red-600 font-medium">
                            ⚠️ This action cannot be undone.
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-2 pt-2">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                @click="cancelDelete"
                            >
                                Cancel
                            </Button>
                            <Button 
                                variant="destructive" 
                                size="sm" 
                                @click="confirmDelete"
                            >
                                <Trash2 class="h-4 w-4 mr-2" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { 
    FileText, CheckCircle, TrendingUp, Users, Search, X, RefreshCw, Download,
    LoaderCircle, MoreHorizontal, Eye, ChevronLeft, ChevronRight, Trash2
} from 'lucide-vue-next';
import { Head } from '@inertiajs/vue3';
import PermissionGuard from '@/components/PermissionGuard.vue';
import { usePermissions } from '@/composables/usePermissions';

interface PayslipRecord {
    id: number;
    name: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    created_at: string;
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
    };
}

const history = ref<PayslipRecord[]>([]);
const isLoading = ref(false);
const isExporting = ref(false);
const isDetailsOpen = ref(false);
const selectedRecord = ref<PayslipRecord | null>(null);
const isDeleteConfirmOpen = ref(false);
const recordToDelete = ref<PayslipRecord | null>(null);
const currentPage = ref(1);
const pageSize = ref(10);

const filters = ref({
    search: '',
    status: '',
    period: 'all'
});

const analytics = computed(() => {
    const completed = history.value.filter(h => h.status === 'completed');
    const totalProcessed = history.value.length;
    const successRate = totalProcessed > 0 ? Math.round((completed.length / totalProcessed) * 100) : 0;
    
    const salaries = completed.filter(h => h.data?.gaji_bersih).map(h => h.data!.gaji_bersih!);
    const avgSalary = salaries.length > 0 ? Math.round(salaries.reduce((sum, salary) => sum + salary, 0) / salaries.length) : 0;
    
    const eligibleCount = completed.filter(h => {
        const results = h.data?.koperasi_results;
        return results && Object.values(results).some(eligible => eligible);
    }).length;

    return {
        totalProcessed,
        successRate,
        avgSalary,
        eligibleCount
    };
});

const filteredHistory = computed(() => {
    let filtered = history.value;

    // Search filter
    if (filters.value.search) {
        const searchTerm = filters.value.search.toLowerCase();
        filtered = filtered.filter(h => 
            h.data?.nama?.toLowerCase().includes(searchTerm) ||
            h.data?.no_gaji?.toLowerCase().includes(searchTerm)
        );
    }

    // Status filter
    if (filters.value.status) {
        filtered = filtered.filter(h => h.status === filters.value.status);
    }

    // Period filter
    if (filters.value.period !== 'all') {
        const now = new Date();
        const filterDate = new Date();
        
        switch (filters.value.period) {
            case 'today':
                filterDate.setHours(0, 0, 0, 0);
                break;
            case 'week':
                filterDate.setDate(now.getDate() - 7);
                break;
            case 'month':
                filterDate.setMonth(now.getMonth() - 1);
                break;
            case 'quarter':
                filterDate.setMonth(now.getMonth() - 3);
                break;
        }
        
        filtered = filtered.filter(h => new Date(h.created_at) >= filterDate);
    }

    return filtered.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
});

const totalPages = computed(() => Math.ceil(filteredHistory.value.length / pageSize.value));

const paginatedHistory = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    const end = start + pageSize.value;
    return filteredHistory.value.slice(start, end);
});

const getStatusClass = (status: string) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400';
        case 'failed': return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400';
        case 'processing': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-400';
    }
};

const getInitials = (name: string): string => {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const fetchHistory = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/payslips', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        });
        if (response.ok) {
            history.value = await response.json();
        }
    } catch (e) {
        // Handle error silently or show user notification
    }
    isLoading.value = false;
};

const refreshData = async () => {
    await fetchHistory();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        status: '',
        period: 'all'
    };
    currentPage.value = 1;
};

const viewDetails = (record: PayslipRecord) => {
    selectedRecord.value = record;
    isDetailsOpen.value = true;
};

const reprocess = async (record: PayslipRecord) => {
    // Implementation for reprocessing
    // Reprocessing record
};

const exportRecord = async (record: PayslipRecord) => {
    // Implementation for exporting single record
    // Exporting record
};

const deleteRecord = (record: PayslipRecord) => {
    recordToDelete.value = record;
    isDeleteConfirmOpen.value = true;
};

const confirmDelete = async () => {
    if (!recordToDelete.value) return;

    try {
        const response = await fetch(`/api/payslips/${recordToDelete.value.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        });

        if (response.ok) {
            // Remove the record from the local array
            const index = history.value.findIndex(h => h.id === recordToDelete.value!.id);
            if (index > -1) {
                history.value.splice(index, 1);
            }
            
            // Show success message (you might want to use a toast notification system)
            console.log('Record deleted successfully');
        } else {
            const error = await response.json();
            console.error('Failed to delete record:', error);
            alert('Failed to delete record. Please try again.');
        }
    } catch (error) {
        console.error('Error deleting record:', error);
        alert('An error occurred while deleting the record. Please try again.');
    } finally {
        // Close the confirmation dialog
        isDeleteConfirmOpen.value = false;
        recordToDelete.value = null;
    }
};

const cancelDelete = () => {
    isDeleteConfirmOpen.value = false;
    recordToDelete.value = null;
};

const exportData = async () => {
    isExporting.value = true;
    try {
        // Simulate export process
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Create CSV content
        const csvContent = [
            ['Name', 'Employee ID', 'Month', 'Gaji Bersih', 'Peratus Gaji Bersih', 'Status', 'Processed At'].join(','),
            ...filteredHistory.value.map(record => [
                record.data?.nama || 'N/A',
                record.data?.no_gaji || 'N/A',
                record.data?.bulan || 'N/A',
                record.data?.gaji_bersih || 'N/A',
                record.data?.peratus_gaji_bersih || 'N/A',
                record.status,
                formatDate(record.created_at)
            ].join(','))
        ].join('\n');

        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `payslip-history-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (e) {
        // Handle export error
    }
    isExporting.value = false;
};

const { 
    canViewAllPayslips, 
    canDeletePayslips, 
    canViewAnalytics,
    canExportAnalytics 
} = usePermissions()

onMounted(() => {
    fetchHistory();
});
</script> 