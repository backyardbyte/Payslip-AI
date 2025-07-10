<template>
    <Head title="History" />
    
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Processing History</h1>
                    <p class="text-muted-foreground">View and analyze past payslip processing results</p>
                </div>
                <div class="flex gap-2">
                    <PermissionGuard permission="analytics.export">
                        <Button variant="outline" size="sm" @click="exportData" :disabled="isExporting">
                            <Download class="h-4 w-4 mr-2" />
                            {{ isExporting ? 'Exporting...' : 'Export CSV' }}
                        </Button>
                    </PermissionGuard>
                    <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading">
                        <RefreshCw :class="['h-4 w-4 mr-2', isLoading && 'animate-spin']" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- Clean History Component -->
            <PermissionGuard permission="payslip.view">
                <CleanHistoryView ref="historyView" />
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
import CleanHistoryView from '@/components/CleanHistoryView.vue';

const isExporting = ref(false);
const isLoading = ref(false);
const historyView = ref(null);

const exportData = async () => {
    isExporting.value = true;
    try {
        // Get data from the API
        const response = await fetch('/api/payslips', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            // Handle the API response structure correctly
            let payslipsData = []
            if (data.payslips) {
                payslipsData = data.payslips
            } else if (data.data) {
                payslipsData = data.data
            } else if (Array.isArray(data)) {
                payslipsData = data
            }
            
            // Create CSV content with more detailed information
            const csvContent = [
                // Headers
                [
                    'Name',
                    'Employee ID',
                    'Month',
                    'Basic Salary',
                    'Total Earnings',
                    'Total Deductions',
                    'Net Salary',
                    'Salary %',
                    'Source',
                    'Status',
                    'Confidence Score',
                    'Processing Time',
                    'Koperasi Eligible',
                    'Processed At'
                ].join(','),
                // Data rows
                ...payslipsData.map((payslip: any) => [
                    // Employee details
                    `"${payslip.data?.nama || 'N/A'}"`,
                    `"${payslip.data?.no_gaji || 'N/A'}"`,
                    `"${payslip.data?.bulan || 'N/A'}"`,
                    // Salary details
                    payslip.data?.gaji_pokok || '0',
                    payslip.data?.jumlah_pendapatan || '0',
                    payslip.data?.jumlah_potongan || '0',
                    payslip.data?.gaji_bersih || '0',
                    payslip.data?.peratus_gaji_bersih || '0',
                    // Processing details
                    payslip.source === 'telegram' ? 'Telegram' : 
                    payslip.source === 'whatsapp' ? 'WhatsApp' :
                    payslip.source === 'web' ? 'Web App' : 'Web App',
                    payslip.status,
                    payslip.quality_metrics?.confidence_score || '0',
                    payslip.quality_metrics?.processing_time || '0',
                    payslip.koperasi_summary ? `${payslip.koperasi_summary.eligible_count}/${payslip.koperasi_summary.total_checked}` : 'N/A',
                    new Date(payslip.created_at).toLocaleString()
                ].join(','))
            ].join('\n');

            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
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
    isLoading.value = true;
    // Call the loadPayslips method on the CleanHistoryView component
    if (historyView.value) {
        (historyView.value as any).loadPayslips().finally(() => {
            isLoading.value = false;
        });
    } else {
        isLoading.value = false;
    }
};
</script> 