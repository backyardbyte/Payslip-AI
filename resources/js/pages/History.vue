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

            <!-- Enhanced History Component -->
            <PermissionGuard permission="payslip.view">
                <EnhancedHistoryView />
            </PermissionGuard>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Download, RefreshCw } from 'lucide-vue-next';
import { Head } from '@inertiajs/vue3';
import PermissionGuard from '@/components/PermissionGuard.vue';
import EnhancedHistoryView from '@/components/EnhancedHistoryView.vue';

const isExporting = ref(false);
const isLoading = ref(false);

const exportData = async () => {
    isExporting.value = true;
    try {
        // Simulate export process
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Get data from the API
        const response = await fetch('/api/payslips', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            const payslips = Array.isArray(data) ? data : (data.payslips || data.data || []);
            
            // Create CSV content
            const csvContent = [
                ['Name', 'Employee ID', 'Month', 'Gaji Bersih', 'Source', 'Status', 'Processed At'].join(','),
                ...payslips.map((payslip: any) => [
                    payslip.extracted_data?.nama || 'N/A',
                    payslip.extracted_data?.no_gaji || 'N/A',
                    payslip.extracted_data?.bulan || 'N/A',
                    payslip.extracted_data?.gaji_bersih || 'N/A',
                    payslip.source === 'telegram' ? 'Telegram' : 
                    payslip.source === 'whatsapp' ? 'WhatsApp' :
                    payslip.source === 'web' ? 'Web App' : 'Web App',
                    payslip.status,
                    new Date(payslip.created_at).toLocaleDateString()
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
        }
    } catch (error) {
        console.error('Export failed:', error);
    } finally {
        isExporting.value = false;
    }
};

const refreshData = () => {
    // The EnhancedHistoryView component will handle its own refresh
    isLoading.value = true;
    setTimeout(() => {
        isLoading.value = false;
    }, 500);
};
</script> 