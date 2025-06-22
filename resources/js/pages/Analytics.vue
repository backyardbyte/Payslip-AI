<template>
    <Head title="Analytics" />
    
    <AppLayout>
        <PermissionGuard permission="analytics.view">
            <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold">Analytics</h1>
                        <p class="text-muted-foreground">Comprehensive insights into payslip processing and eligibility</p>
                    </div>
                    <div class="flex gap-2">
                        <select v-model="dateRange" class="px-3 py-2 border border-input rounded-md bg-background text-sm">
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                        </select>
                        <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                            <RefreshCw :class="['h-4 w-4 mr-2', isLoading && 'animate-spin']" />
                            Refresh
                        </Button>
                    </div>
                </div>

                <!-- Key Metrics -->
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
                                    <p class="text-xs text-green-600">+{{ analytics.recentGrowth }}% vs last period</p>
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
                                    <p class="text-xs text-green-600">{{ analytics.successRate >= 95 ? 'Excellent' : analytics.successRate >= 85 ? 'Good' : 'Needs improvement' }}</p>
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
                                    <p class="text-xs text-muted-foreground">{{ Math.round((analytics.eligibleCount / analytics.totalProcessed) * 100) }}% eligibility rate</p>
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
                                    <p class="text-sm text-muted-foreground">Avg. Percentage</p>
                                    <p class="text-2xl font-bold">{{ analytics.avgPercentage }}%</p>
                                    <p class="text-xs text-muted-foreground">Average gaji bersih percentage</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Processing Trends -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Processing Trends</CardTitle>
                            <CardDescription>Daily processing volume over time</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="h-64 flex items-center justify-center border border-dashed border-muted-foreground rounded-lg">
                                <div class="text-center text-muted-foreground">
                                    <BarChart3 class="h-12 w-12 mx-auto mb-2" />
                                    <p>Processing trends chart</p>
                                    <p class="text-sm">Chart visualization would be here</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Success Rate Breakdown -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Success Rate by Koperasi</CardTitle>
                            <CardDescription>Eligibility success rates by institution</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div v-for="koperasi in analytics.koperasiStats" :key="koperasi.name" class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                                            <span class="text-xs font-semibold text-primary">{{ koperasi.name.substring(0, 2).toUpperCase() }}</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">{{ koperasi.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ koperasi.totalChecked }} applications</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold">{{ koperasi.successRate }}%</p>
                                        <div class="w-20 h-2 bg-muted rounded-full mt-1">
                                            <div class="h-full bg-primary rounded-full" :style="{ width: `${koperasi.successRate}%` }"></div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="analytics.koperasiStats.length === 0" class="text-center text-muted-foreground py-8">
                                    <Users class="h-8 w-8 mx-auto mb-2" />
                                    <p>No koperasi data available</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Detailed Statistics -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Processing Status -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Processing Status</CardTitle>
                            <CardDescription>Current queue and processing status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Completed</span>
                                    <span class="text-sm font-bold text-green-600">{{ analytics.statusBreakdown.completed }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Processing</span>
                                    <span class="text-sm font-bold text-blue-600">{{ analytics.statusBreakdown.processing }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Queued</span>
                                    <span class="text-sm font-bold text-yellow-600">{{ analytics.statusBreakdown.queued }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Failed</span>
                                    <span class="text-sm font-bold text-red-600">{{ analytics.statusBreakdown.failed }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Salary Distribution -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Salary Distribution</CardTitle>
                            <CardDescription>Average salary insights</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Average Gaji Bersih</span>
                                    <span class="text-sm font-bold">RM {{ analytics.salaryStats.avgGajiBersih.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Highest</span>
                                    <span class="text-sm font-bold text-green-600">RM {{ analytics.salaryStats.highest.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Lowest</span>
                                    <span class="text-sm font-bold text-red-600">RM {{ analytics.salaryStats.lowest.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm">Median</span>
                                    <span class="text-sm font-bold">RM {{ analytics.salaryStats.median.toLocaleString() }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Activity -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                            <CardDescription>Latest processing activities</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div v-for="activity in analytics.recentActivity" :key="activity.id" class="flex items-center space-x-3">
                                    <div :class="['w-2 h-2 rounded-full', getStatusColor(activity.status)]"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm truncate">{{ activity.name || 'Unknown' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatTime(activity.time) }}</p>
                                    </div>
                                    <span :class="['text-xs px-2 py-1 rounded-full', getStatusBadgeClass(activity.status)]">
                                        {{ activity.status }}
                                    </span>
                                </div>
                                <div v-if="analytics.recentActivity.length === 0" class="text-center text-muted-foreground py-4">
                                    <Clock class="h-6 w-6 mx-auto mb-1" />
                                    <p class="text-sm">No recent activity</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PermissionGuard>

        <!-- Access Denied Message -->
        <PermissionGuard permission="analytics.view" fallback>
            <div class="flex flex-col h-full items-center justify-center p-8">
                <Card class="w-full max-w-md">
                    <CardContent class="p-8 text-center">
                        <div class="flex flex-col items-center gap-4">
                            <div class="p-4 bg-muted rounded-full">
                                <Shield class="h-8 w-8 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Access Restricted</h3>
                                <p class="text-muted-foreground">You don't have permission to view analytics.</p>
                                <p class="text-sm text-muted-foreground mt-2">Contact your administrator for access.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PermissionGuard>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { 
    FileText, CheckCircle, Users, TrendingUp, BarChart3, RefreshCw, Clock, Shield
} from 'lucide-vue-next';
import { Head } from '@inertiajs/vue3';
import PermissionGuard from '@/components/PermissionGuard.vue';
import { usePermissions } from '@/composables/usePermissions';

const dateRange = ref('30');
const isLoading = ref(false);

const analytics = ref({
    totalProcessed: 0,
    successRate: 0,
    eligibleCount: 0,
    avgPercentage: 0,
    recentGrowth: 0,
    statusBreakdown: {
        completed: 0,
        processing: 0,
        queued: 0,
        failed: 0
    },
    koperasiStats: [] as Array<{
        name: string;
        successRate: number;
        totalChecked: number;
    }>,
    salaryStats: {
        avgGajiBersih: 0,
        highest: 0,
        lowest: 0,
        median: 0
    },
    recentActivity: [] as Array<{
        id: number;
        name: string;
        status: string;
        time: string;
    }>
});

const { canViewAnalytics, canExportAnalytics } = usePermissions();

const fetchAnalytics = async () => {
    isLoading.value = true;
    try {
        const response = await fetch(`/api/system/statistics?days=${dateRange.value}`);
        if (response.ok) {
            const data = await response.json();
            
            // Map API response to analytics structure
            analytics.value = {
                totalProcessed: data.payslips?.total || 0,
                successRate: data.processing?.success_rate || 0,
                eligibleCount: Math.floor((data.payslips?.completed || 0) * 0.7), // Estimate
                avgPercentage: 85.5, // Mock data
                recentGrowth: 12, // Mock data
                statusBreakdown: {
                    completed: data.payslips?.completed || 0,
                    processing: data.payslips?.processing || 0,
                    queued: data.payslips?.queued || 0,
                    failed: data.payslips?.failed || 0
                },
                koperasiStats: [
                    { name: 'KOPKRAJAAN', successRate: 87, totalChecked: 156 },
                    { name: 'KOPUTRA', successRate: 92, totalChecked: 134 },
                    { name: 'KOPERASI MAMPU', successRate: 78, totalChecked: 89 },
                ], // Mock data
                salaryStats: {
                    avgGajiBersih: 3250,
                    highest: 8500,
                    lowest: 1200,
                    median: 3100
                }, // Mock data
                recentActivity: [] // Will be populated from recent payslips
            };
        }
    } catch (e) {
        // Handle error silently or show user notification
    }
    isLoading.value = false;
};

const refreshData = async () => {
    await fetchAnalytics();
};

const getStatusColor = (status: string): string => {
    switch (status) {
        case 'completed': return 'bg-green-500';
        case 'processing': return 'bg-blue-500';
        case 'queued': return 'bg-yellow-500';
        case 'failed': return 'bg-red-500';
        default: return 'bg-gray-500';
    }
};

const getStatusBadgeClass = (status: string): string => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400';
        case 'processing': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400';
        case 'queued': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-400';
        case 'failed': return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-400';
    }
};

const formatTime = (timeString: string): string => {
    return new Date(timeString).toLocaleString('en-MY', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: 'short'
    });
};

onMounted(() => {
    fetchAnalytics();
});
</script> 