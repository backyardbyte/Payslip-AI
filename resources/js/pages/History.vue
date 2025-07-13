<template>
    <Head title="AI Processing History" />
    
    <AppLayout>
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <!-- Enhanced Header with AI Branding -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">AI Processing History</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-muted-foreground">Powered by Google Vision API for intelligent document analysis</p>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center gap-1.5 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full dark:bg-blue-900/50 dark:text-blue-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                                    </svg>
                                    Google Vision API
                                </span>
                                <span class="inline-flex items-center gap-1.5 bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full dark:bg-purple-900/50 dark:text-purple-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    AI Analysis
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <PermissionGuard permission="analytics.export">
                        <Button variant="outline" size="sm" @click="exportData" :disabled="isExporting" class="flex items-center gap-2">
                            <Download class="h-4 w-4" />
                            {{ isExporting ? 'Exporting AI Data...' : 'Export AI Report' }}
                        </Button>
                    </PermissionGuard>
                    <Button variant="outline" size="sm" @click="refreshData" :disabled="isLoading" class="flex items-center gap-2">
                        <RefreshCw :class="['h-4 w-4', isLoading && 'animate-spin']" />
                        {{ isLoading ? 'Refreshing...' : 'Refresh' }}
                    </Button>
                </div>
            </div>

            <!-- AI Technology Information Banner -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-500/10 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Advanced AI Document Processing</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
                            This system uses Google Cloud Vision API to intelligently extract and analyze data from Malaysian payslips with industry-leading accuracy.
                        </p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">99%+ Accuracy</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Real-time Processing</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Smart Analytics</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-blue-900 dark:text-blue-100">Secure Processing</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Processing History Component -->
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
            
            // Create enhanced CSV content with AI processing details
            const csvContent = [
                // Headers with AI processing information
                [
                    'File Name',
                    'Employee Name',
                    'Employee ID',
                    'Pay Period',
                    'Basic Salary (RM)',
                    'Total Earnings (RM)',
                    'Total Deductions (RM)',
                    'Net Salary (RM)',
                    'Salary Percentage (%)',
                    'Upload Source',
                    'Processing Status',
                    'Google Vision API Confidence (%)',
                    'AI Processing Time (seconds)',
                    'Data Completeness (%)',
                    'Koperasi Eligible Count',
                    'Total Koperasi Checked',
                    'Processing Method',
                    'Processed Date',
                    'Created Date'
                ].join(','),
                // Data rows with enhanced AI processing information
                ...payslipsData.map((payslip: any) => [
                    // File and employee details
                    `"${payslip.name || 'Unknown File'}"`,
                    `"${payslip.data?.nama || 'Not extracted by AI'}"`,
                    `"${payslip.data?.no_gaji || 'Not extracted by AI'}"`,
                    `"${payslip.data?.bulan || 'Not extracted by AI'}"`,
                    // AI-extracted salary details
                    payslip.data?.gaji_pokok || '0',
                    payslip.data?.jumlah_pendapatan || '0',
                    payslip.data?.jumlah_potongan || '0',
                    payslip.data?.gaji_bersih || '0',
                    payslip.data?.peratus_gaji_bersih || '0',
                    // Processing source and status
                    payslip.source === 'telegram' ? 'Telegram Bot' : 
                    payslip.source === 'whatsapp' ? 'WhatsApp Bot' :
                    payslip.source === 'web' ? 'Web Application' : 'Web Application',
                    payslip.status === 'completed' ? 'AI Processed Successfully' :
                    payslip.status === 'failed' ? 'AI Processing Failed' :
                    payslip.status === 'processing' ? 'AI Processing in Progress' :
                    payslip.status === 'queued' ? 'Queued for AI Processing' : payslip.status,
                    // AI quality metrics
                    Math.round(payslip.quality_metrics?.confidence_score || 0),
                    payslip.quality_metrics?.processing_time || '0',
                    Math.round(payslip.quality_metrics?.data_completeness || 0),
                    // Koperasi analysis results
                    payslip.koperasi_summary?.eligible_count || '0',
                    payslip.koperasi_summary?.total_checked || '0',
                    'Google Vision API',
                    // Timestamps
                    payslip.processing_completed_at ? new Date(payslip.processing_completed_at).toLocaleString() : 'Not completed',
                    new Date(payslip.created_at).toLocaleString()
                ].join(','))
            ].join('\n');

            // Download enhanced CSV with AI processing details
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ai-payslip-processing-report-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
            
            console.log('AI processing report exported successfully');
        }
    } catch (error) {
        console.error('Export failed:', error);
        // You could show a toast notification here
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