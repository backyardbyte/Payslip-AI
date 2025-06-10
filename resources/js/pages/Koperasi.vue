<template>
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/50">
                                <Landmark class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total Koperasi</p>
                                <p class="text-2xl font-bold">{{ koperasi.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/50">
                                <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Active</p>
                                <p class="text-2xl font-bold">{{ activeKoperasi.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-red-100 rounded-lg dark:bg-red-900/50">
                                <XCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Inactive</p>
                                <p class="text-2xl font-bold">{{ inactiveKoperasi.length }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-purple-100 rounded-lg dark:bg-purple-900/50">
                                <TrendingUp class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Avg Max Rate</p>
                                <p class="text-2xl font-bold">{{ averageMaxRate }}%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Header with Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Koperasi Management</h1>
                    <p class="text-muted-foreground">Manage eligibility rules and rates for all koperasi institutions</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                        <RefreshCw :class="['h-4 w-4 mr-2', isLoading && 'animate-spin']" />
                        Refresh
                    </Button>
                    <Button size="sm">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Koperasi
                    </Button>
                </div>
            </div>

            <!-- Search and Filter -->
            <Card>
                <CardContent class="p-4">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search koperasi by name..."
                                    class="w-full pl-10 pr-4 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                :class="statusFilter === 'all' ? 'bg-primary text-primary-foreground' : ''"
                                @click="statusFilter = 'all'"
                            >
                                All
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                :class="statusFilter === 'active' ? 'bg-green-600 text-white' : ''"
                                @click="statusFilter = 'active'"
                            >
                                Active
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                :class="statusFilter === 'inactive' ? 'bg-red-600 text-white' : ''"
                                @click="statusFilter = 'inactive'"
                            >
                                Inactive
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Koperasi List -->
            <Card class="flex-1">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Koperasi Registry</CardTitle>
                            <CardDescription>
                                {{ filteredKoperasi.length }} of {{ koperasi.length }} koperasi shown
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="icon" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-muted' : ''">
                                <LayoutGrid class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-muted' : ''">
                                <TableIcon class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Card v-for="item in filteredKoperasi" :key="item.id" class="group hover:shadow-md transition-shadow">
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center space-x-2">
                                        <div :class="['w-3 h-3 rounded-full', item.is_active ? 'bg-green-500' : 'bg-red-500']"></div>
                                        <CardTitle class="text-lg">{{ item.name }}</CardTitle>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem>
                                                <Edit class="h-4 w-4 mr-2" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem>
                                                <Copy class="h-4 w-4 mr-2" />
                                                Duplicate
                                            </DropdownMenuItem>
                                            <DropdownMenuItem class="text-red-600">
                                                <Trash2 class="h-4 w-4 mr-2" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                                <div class="mt-2">
                                    <span :class="['px-2 py-1 text-xs font-medium rounded-full', item.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400']">
                                        {{ item.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-medium text-muted-foreground">Eligibility Rules</h4>
                                        <div class="space-y-1">
                                            <div v-for="([key, value]) in Object.entries(item.rules)" :key="key" class="flex justify-between text-sm">
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
                                <TableRow class="hover:bg-transparent border-b-0">
                                    <TableHead class="w-[300px]">Koperasi</TableHead>
                                    <TableHead>Eligibility Rules</TableHead>
                                    <TableHead class="w-[120px] text-center">Status</TableHead>
                                    <TableHead class="w-[140px] text-center">Last Updated</TableHead>
                                    <TableHead class="w-[80px] text-right">Actions</TableHead>
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
                                        <div class="space-y-1">
                                            <div v-for="([key, value]) in Object.entries(item.rules)" :key="key" class="flex justify-between text-sm">
                                                <span class="text-muted-foreground">{{ formatKey(key) }}:</span>
                                                <span class="font-medium ml-2">{{ formatValue(key, value) }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded-full', item.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400']">
                                            {{ item.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <div class="text-sm">{{ formatDate(item.updated_at) }}</div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem>
                                                    <Edit class="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <Copy class="h-4 w-4 mr-2" />
                                                    Duplicate
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="text-red-600">
                                                    <Trash2 class="h-4 w-4 mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { 
    Landmark, CheckCircle, XCircle, TrendingUp, RefreshCw, Plus, Search, 
    LayoutGrid, Table2 as TableIcon, MoreHorizontal, Edit, Copy, Trash2 
} from 'lucide-vue-next';

interface Koperasi {
    id: number;
    name: string;
    rules: Record<string, any>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    koperasi: Koperasi[];
}>();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const viewMode = ref<'grid' | 'table'>('table');
const isLoading = ref(false);

const activeKoperasi = computed(() => props.koperasi.filter(k => k.is_active));
const inactiveKoperasi = computed(() => props.koperasi.filter(k => !k.is_active));

const averageMaxRate = computed(() => {
    const rates = props.koperasi
        .filter(k => k.rules.peratus_gaji_bersih)
        .map(k => k.rules.peratus_gaji_bersih);
    
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

const refreshData = async () => {
    isLoading.value = true;
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000));
    isLoading.value = false;
};
</script> 