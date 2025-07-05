<template>
    <Head title="Koperasi" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <Card class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-xs">Total Koperasi</p>
                                    <p class="text-xl font-bold">{{ koperasi.length }}</p>
                                </div>
                                <Landmark class="h-6 w-6 text-blue-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-green-500 to-green-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-xs">Active</p>
                                    <p class="text-xl font-bold">{{ activeKoperasi.length }}</p>
                                </div>
                                <CheckCircle class="h-6 w-6 text-green-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-red-500 to-red-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-red-100 text-xs">Inactive</p>
                                    <p class="text-xl font-bold">{{ inactiveKoperasi.length }}</p>
                                </div>
                                <XCircle class="h-6 w-6 text-red-200" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                        <CardContent class="p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-100 text-xs">Avg Max Rate</p>
                                    <p class="text-xl font-bold">{{ averageMaxRate }}%</p>
                                </div>
                                <TrendingUp class="h-6 w-6 text-purple-200" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Header with Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold">Koperasi Management</h1>
                        <p class="text-muted-foreground">
                            <PermissionGuard permission="koperasi.create">
                                Manage eligibility rules and rates for all koperasi institutions
                            </PermissionGuard>
                            <PermissionGuard permission="koperasi.create" fallback>
                                View eligibility rules and rates for all koperasi institutions
                            </PermissionGuard>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading" class="h-7 text-xs">
                            <RefreshCw :class="['h-3 w-3 mr-1.5', isLoading && 'animate-spin']" />
                            Refresh
                        </Button>
                        <PermissionGuard permission="koperasi.create">
                            <Button size="sm" @click="openAddModal" class="h-7 text-xs">
                                <Plus class="h-3 w-3 mr-1.5" />
                                Add Koperasi
                            </Button>
                        </PermissionGuard>
                    </div>
                </div>

                <!-- Search and Filter -->
                <Card>
                    <CardContent class="p-3">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Search koperasi by name..."
                                        class="w-full pl-8 pr-3 py-1.5 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                                    />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    :class="statusFilter === 'all' ? 'bg-primary text-primary-foreground' : ''"
                                    @click="statusFilter = 'all'"
                                    class="h-7 text-xs"
                                >
                                    All
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    :class="statusFilter === 'active' ? 'bg-green-600 text-white' : ''"
                                    @click="statusFilter = 'active'"
                                    class="h-7 text-xs"
                                >
                                    Active
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    :class="statusFilter === 'inactive' ? 'bg-red-600 text-white' : ''"
                                    @click="statusFilter = 'inactive'"
                                    class="h-7 text-xs"
                                >
                                    Inactive
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Koperasi List -->
                <Card class="flex-1">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Koperasi Registry</CardTitle>
                                <CardDescription class="text-xs">
                                    {{ filteredKoperasi.length }} of {{ koperasi.length }} koperasi shown
                                </CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button variant="ghost" size="icon" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-muted' : ''" class="h-7 w-7">
                                    <LayoutGrid class="h-3 w-3" />
                                </Button>
                                <Button variant="ghost" size="icon" @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-muted' : ''" class="h-7 w-7">
                                    <TableIcon class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Grid View -->
                        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <Card v-for="item in filteredKoperasi" :key="item.id" class="group hover:shadow-md transition-shadow">
                                <CardHeader class="pb-2">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center space-x-2">
                                            <div :class="['w-3 h-3 rounded-full', item.is_active ? 'bg-green-500' : 'bg-red-500']"></div>
                                            <CardTitle class="text-sm">{{ item.name }}</CardTitle>
                                        </div>
                                        <PermissionGuard :permissions="['koperasi.update', 'koperasi.delete']">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6">
                                                        <MoreHorizontal class="h-3 w-3" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <PermissionGuard permission="koperasi.update">
                                                        <DropdownMenuItem @click="openEditModal(item)">
                                                            <Edit class="h-3 w-3 mr-2" />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem @click="duplicateKoperasi(item)">
                                                            <Copy class="h-3 w-3 mr-2" />
                                                            Duplicate
                                                        </DropdownMenuItem>
                                                    </PermissionGuard>
                                                    <PermissionGuard permission="koperasi.delete">
                                                        <DropdownMenuItem class="text-red-600" @click="confirmDelete(item)">
                                                            <Trash2 class="h-3 w-3 mr-2" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </PermissionGuard>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </PermissionGuard>
                                    </div>
                                    <div class="mt-1">
                                        <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', item.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400']">
                                            {{ item.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <div class="space-y-2">
                                            <h4 class="text-sm font-medium text-muted-foreground">Eligibility Rules</h4>
                                            <div class="space-y-1">
                                                <div v-for="([key, value]) in getFilteredRules(item.rules)" :key="key" class="flex justify-between text-sm">
                                                    <span class="text-muted-foreground">{{ formatKey(key) }}:</span>
                                                    <span class="font-medium">
                                                        {{ formatValue(key, value) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pt-2 border-t">
                                            <div class="text-xs text-muted-foreground">
                                                Last updated: {{ formatDate(item.updated_at) }}
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Table View -->
                        <div v-else class="overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Peratus Gaji Bersih</TableHead>
                                        <TableHead>Updated</TableHead>
                                        <PermissionGuard :permissions="['koperasi.update', 'koperasi.delete']">
                                            <TableHead class="text-right">Actions</TableHead>
                                        </PermissionGuard>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in filteredKoperasi" :key="item.id" class="group">
                                        <TableCell>
                                            <div class="flex items-center space-x-3">
                                                <div :class="['w-3 h-3 rounded-full', item.is_active ? 'bg-green-500' : 'bg-red-500']"></div>
                                                <div>
                                                    <div class="font-medium">{{ item.name }}</div>
                                                    <div class="text-xs text-muted-foreground">ID: {{ item.id }}</div>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="text-sm">{{ item.is_active ? 'Active' : 'Inactive' }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="text-sm">{{ getPercentageDisplay(item.rules) }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="text-sm">{{ formatDate(item.updated_at) }}</div>
                                        </TableCell>
                                        <PermissionGuard :permissions="['koperasi.update', 'koperasi.delete']">
                                            <TableCell class="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <MoreHorizontal class="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <PermissionGuard permission="koperasi.update">
                                                            <DropdownMenuItem @click="openEditModal(item)">
                                                                <Edit class="h-4 w-4 mr-2" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                        </PermissionGuard>
                                                        <PermissionGuard permission="koperasi.delete">
                                                            <DropdownMenuItem class="text-red-600" @click="confirmDelete(item)">
                                                                <Trash2 class="h-4 w-4 mr-2" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </PermissionGuard>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </PermissionGuard>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>



        <!-- Add/Edit Modal -->
        <Dialog v-model:open="showModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Koperasi' : 'Add New Koperasi' }}</DialogTitle>
                    <DialogDescription>
                        {{ isEditing ? 'Update the koperasi information and eligibility rules.' : 'Create a new koperasi with eligibility rules.' }}
                    </DialogDescription>
                </DialogHeader>
                
                <form @submit.prevent="submitForm" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium">Koperasi Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Enter koperasi name"
                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                required
                            />
                            <p v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name }}</p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                id="is_active"
                                class="rounded border-input"
                            />
                            <label for="is_active" class="text-sm font-medium">Active</label>
                        </div>
                    </div>

                    <!-- Eligibility Rules -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium">Eligibility Rules</h3>
                            <Button type="button" variant="outline" size="sm" @click="addRule">
                                <Plus class="h-4 w-4 mr-2" />
                                Add Rule
                            </Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(rule, index) in form.rules" :key="index" class="flex gap-3 items-start">
                                <div class="flex-1">
                                    <select
                                        v-model="rule.key"
                                        class="w-full px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        required
                                    >
                                        <option value="">Select rule type</option>
                                        <option value="peratus_gaji_bersih">Peratus Gaji Bersih (%)</option>
                                        <option value="min_gaji_pokok">Min Gaji Pokok (RM)</option>
                                        <option value="max_umur">Max Age (years)</option>
                                        <option value="min_tenure_months">Min Tenure (months)</option>
                                        <option value="max_loan_amount">Max Loan Amount (RM)</option>
                                        <option value="min_working_years">Min Working Years</option>
                                        <option value="max_debt_service_ratio">Max Debt Service Ratio (%)</option>
                                        <option value="credit_score_min">Min Credit Score</option>
                                        <option value="max_loan_multiplier">Max Loan Multiplier</option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <input
                                        v-model="rule.value"
                                        type="number"
                                        step="0.01"
                                        placeholder="Enter value"
                                        class="w-full px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        required
                                    />
                                </div>
                                <Button type="button" variant="ghost" size="icon" @click="removeRule(index)" class="text-red-600">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                        <p v-if="errors.rules" class="text-sm text-red-600">{{ errors.rules }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeModal">Cancel</Button>
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:open="showDeleteModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Koperasi</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ itemToDelete?.name }}"? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showDeleteModal = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteKoperasi" :disabled="processing">
                        {{ processing ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { 
    Landmark, CheckCircle, XCircle, TrendingUp, RefreshCw, Plus, Search, 
    LayoutGrid, Table2 as TableIcon, MoreHorizontal, Edit, Copy, Trash2, Shield
} from 'lucide-vue-next';
import { Head } from '@inertiajs/vue3';
import PermissionGuard from '@/components/PermissionGuard.vue';
import { usePermissions } from '@/composables/usePermissions';

interface Koperasi {
    id: number;
    name: string;
    rules: Record<string, any>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface KoperasiRule {
    key: string;
    value: number;
}

const props = defineProps<{
    koperasi: Koperasi[];
}>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const viewMode = ref<'grid' | 'table'>('table');
const isLoading = ref(false);
const showModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const processing = ref(false);
const itemToDelete = ref<Koperasi | null>(null);
const errors = ref<Record<string, string>>({});

const form = reactive({
    id: null as number | null,
    name: '',
    rules: [] as KoperasiRule[],
    is_active: true,
});

const { 
    canViewKoperasi, 
    canCreateKoperasi, 
    canUpdateKoperasi, 
    canDeleteKoperasi, 
    canManageKoperasiRules 
} = usePermissions()

const activeKoperasi = computed(() => props.koperasi.filter(k => k.is_active));
const inactiveKoperasi = computed(() => props.koperasi.filter(k => !k.is_active));

const averageMaxRate = computed(() => {
    const rates = props.koperasi
        .filter(k => k.rules.max_peratus_gaji_bersih || k.rules.peratus_gaji_bersih)
        .map(k => k.rules.max_peratus_gaji_bersih || k.rules.peratus_gaji_bersih);
    
    if (rates.length === 0) return 0;
    
    const average = rates.reduce((sum, rate) => sum + rate, 0) / rates.length;
    return Math.round(average * 10) / 10;
});

const filteredKoperasi = computed(() => {
    let filtered = props.koperasi;

    // Filter by search query
    if (searchQuery.value) {
        filtered = filtered.filter(k => 
            k.name.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }

    // Filter by status
    if (statusFilter.value === 'active') {
        filtered = filtered.filter(k => k.is_active);
    } else if (statusFilter.value === 'inactive') {
        filtered = filtered.filter(k => !k.is_active);
    }

    return filtered;
});

const formatKey = (key: string): string => {
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase());
};

const formatValue = (key: string, value: any): string => {
    if (key.includes('peratus') || key.includes('rate')) {
        return `${value}%`;
    }
    if (key.includes('gaji') || key.includes('salary') || key.includes('min_') || key.includes('max_')) {
        return `RM ${Number(value).toLocaleString()}`;
    }
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }
    return String(value);
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
};

const getPercentageDisplay = (rules: Record<string, any>): string => {
    // Check for various possible field names for percentage
    const percentage = rules.peratus_gaji_bersih || 
                      rules.max_peratus_gaji_bersih || 
                      rules.min_peratus_gaji_bersih;
    
    if (percentage !== undefined && percentage !== null) {
        return `${percentage}%`;
    }
    return 'N/A';
};

const refreshData = async () => {
    isLoading.value = true;
    router.reload({ only: ['koperasi'] });
    setTimeout(() => {
        isLoading.value = false;
    }, 1000);
};

const getFilteredRules = (rules: Record<string, any>): [string, any][] => {
    const entries = Object.entries(rules);
    
    // If user doesn't have management permissions, filter out sensitive fields
    if (!canManageKoperasiRules) {
        return entries.filter(([key]) => !key.includes('min_gaji') && !key.includes('min_salary'));
    }
    
    return entries;
};

const resetForm = () => {
    form.id = null;
    form.name = '';
    form.rules = [];
    form.is_active = true;
    errors.value = {};
};

const openAddModal = () => {
    resetForm();
    isEditing.value = false;
    showModal.value = true;
};

const openEditModal = (koperasi: Koperasi) => {
    resetForm();
    form.id = koperasi.id;
    form.name = koperasi.name;
    form.is_active = koperasi.is_active;
    form.rules = Object.entries(koperasi.rules).map(([key, value]) => ({ key, value: Number(value) }));
    isEditing.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    resetForm();
};

const addRule = () => {
    form.rules.push({ key: '', value: 0 });
};

const removeRule = (index: number) => {
    form.rules.splice(index, 1);
};

const duplicateKoperasi = (koperasi: Koperasi) => {
    resetForm();
    form.name = `${koperasi.name} (Copy)`;
    form.is_active = koperasi.is_active;
    form.rules = Object.entries(koperasi.rules).map(([key, value]) => ({ key, value: Number(value) }));
    isEditing.value = false;
    showModal.value = true;
};

const submitForm = () => {
    processing.value = true;
    errors.value = {};

    // Convert rules array to object
    const rulesObject = form.rules.reduce((acc, rule) => {
        if (rule.key && rule.value !== null) {
            acc[rule.key] = rule.value;
        }
        return acc;
    }, {} as Record<string, number>);

    const data = {
        name: form.name,
        rules: rulesObject,
        is_active: form.is_active,
    };

    const url = isEditing.value ? `/koperasi/${form.id}` : '/koperasi';
    const method = isEditing.value ? 'put' : 'post';

    router[method](url, data, {
        onSuccess: () => {
            closeModal();
            processing.value = false;
        },
        onError: (pageErrors) => {
            errors.value = pageErrors as Record<string, string>;
            processing.value = false;
        },
    });
};

const confirmDelete = (koperasi: Koperasi) => {
    itemToDelete.value = koperasi;
    showDeleteModal.value = true;
};

const deleteKoperasi = () => {
    if (!itemToDelete.value) return;

    processing.value = true;
    router.delete(`/koperasi/${itemToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            itemToDelete.value = null;
            processing.value = false;
        },
        onError: () => {
            processing.value = false;
        },
    });
};
</script> 