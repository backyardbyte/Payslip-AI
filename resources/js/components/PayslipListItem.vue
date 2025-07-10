<template>
  <Card class="hover:shadow-md transition-all duration-200 border-l-4"
        :class="[getStatusBorderColor(payslip.status)]">
    <CardContent class="p-0">
      <!-- Header Row -->
      <div 
        class="flex items-center gap-4 p-4 cursor-pointer hover:bg-muted/50 transition-colors"
        @click="$emit('expand', payslip.id)"
      >
        <!-- File Icon & Info -->
        <div class="flex items-center gap-4 flex-1">
          <div class="relative">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg flex items-center justify-center">
              <FileText class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div v-if="payslip.source" 
                 class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center"
                 :class="getSourceBadgeColor(payslip.source)">
              <component :is="getSourceIcon(payslip.source)" class="h-3 w-3 text-white" />
            </div>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-sm truncate">{{ payslip.name }}</h3>
              <Badge :variant="getStatusVariant(payslip.status)" class="text-xs">
                {{ formatStatus(payslip.status) }}
              </Badge>
            </div>
            <div class="flex items-center gap-4 mt-1 text-xs text-muted-foreground">
              <span class="flex items-center gap-1">
                <User class="h-3 w-3" />
                {{ payslip.extracted_data?.nama || 'Unknown' }}
              </span>
              <span class="flex items-center gap-1">
                <Hash class="h-3 w-3" />
                {{ payslip.extracted_data?.no_gaji || 'N/A' }}
              </span>
              <span class="flex items-center gap-1">
                <CalendarDays class="h-3 w-3" />
                {{ payslip.extracted_data?.bulan || formatDate(payslip.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Key Metrics -->
        <div class="hidden lg:flex items-center gap-6">
          <!-- Net Salary -->
          <div class="text-center">
            <div class="text-sm font-semibold">
              {{ formatCurrency(payslip.extracted_data?.gaji_bersih) }}
            </div>
            <div class="text-xs text-muted-foreground">Net Salary</div>
          </div>

          <!-- Confidence Score -->
          <div class="text-center">
            <div class="flex items-center gap-1">
              <div class="text-sm font-semibold">
                {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
              </div>
              <div :class="getConfidenceIndicatorColor(payslip.quality_metrics?.confidence_score || 0)">
                <TrendingUp v-if="(payslip.quality_metrics?.confidence_score || 0) >= 80" class="h-3 w-3" />
                <Minus v-else-if="(payslip.quality_metrics?.confidence_score || 0) >= 60" class="h-3 w-3" />
                <TrendingDown v-else class="h-3 w-3" />
              </div>
            </div>
            <div class="text-xs text-muted-foreground">Confidence</div>
          </div>

          <!-- Koperasi Eligibility -->
          <div v-if="payslip.koperasi_summary" class="text-center">
            <div class="flex items-center gap-1">
              <CheckCircle class="h-4 w-4 text-green-500" />
              <span class="text-sm font-semibold text-green-600">
                {{ payslip.koperasi_summary.eligible_count }}/{{ payslip.koperasi_summary.total_checked }}
              </span>
            </div>
            <div class="text-xs text-muted-foreground">Eligible</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <Button
            v-if="payslip.status === 'failed'"
            variant="ghost"
            size="icon"
            @click.stop="$emit('reprocess', payslip)"
            class="h-8 w-8"
          >
            <RefreshCw class="h-4 w-4" />
          </Button>
          
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" class="h-8 w-8">
                <MoreVertical class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-48">
              <DropdownMenuItem @click="$emit('download', payslip)">
                <Download class="h-4 w-4 mr-2" />
                Download PDF
              </DropdownMenuItem>
              <DropdownMenuItem @click="copyToClipboard(payslip)">
                <Copy class="h-4 w-4 mr-2" />
                Copy Details
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="$emit('delete', payslip)" class="text-red-600">
                <Trash2 class="h-4 w-4 mr-2" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <ChevronRight 
            class="h-4 w-4 text-muted-foreground transition-transform duration-200"
            :class="{ 'rotate-90': isExpanded }"
          />
        </div>
      </div>

      <!-- Expanded Content -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="transform opacity-0 max-h-0"
        enter-to-class="transform opacity-100 max-h-[1000px]"
        leave-active-class="transition-all duration-200 ease-out"
        leave-from-class="transform opacity-100 max-h-[1000px]"
        leave-to-class="transform opacity-0 max-h-0"
      >
        <div v-if="isExpanded" class="border-t bg-muted/30">
          <div class="p-6 space-y-6">
            <!-- Processing Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Card>
                <CardContent class="p-4">
                  <h4 class="text-sm font-medium mb-3 flex items-center gap-2">
                    <Info class="h-4 w-4" />
                    Processing Details
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">File Size:</span>
                      <span class="font-medium">{{ formatFileSize(payslip.size) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Uploaded:</span>
                      <span class="font-medium">{{ formatDateTime(payslip.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Processing Time:</span>
                      <span class="font-medium">{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Data Completeness:</span>
                      <span class="font-medium">{{ payslip.quality_metrics?.data_completeness || 0 }}%</span>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardContent class="p-4">
                  <h4 class="text-sm font-medium mb-3 flex items-center gap-2">
                    <Wallet class="h-4 w-4" />
                    Salary Breakdown
                  </h4>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Basic Salary:</span>
                      <span class="font-medium">{{ formatCurrency(payslip.extracted_data?.gaji_pokok) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Total Earnings:</span>
                      <span class="font-medium">{{ formatCurrency(payslip.extracted_data?.jumlah_pendapatan) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-muted-foreground">Total Deductions:</span>
                      <span class="font-medium text-red-600">{{ formatCurrency(payslip.extracted_data?.jumlah_potongan) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t">
                      <span class="text-muted-foreground">Net Salary:</span>
                      <span class="font-bold text-green-600">{{ formatCurrency(payslip.extracted_data?.gaji_bersih) }}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardContent class="p-4">
                  <h4 class="text-sm font-medium mb-3 flex items-center gap-2">
                    <Building2 class="h-4 w-4" />
                    Koperasi Summary
                  </h4>
                  <div v-if="payslip.koperasi_summary" class="space-y-3">
                    <div class="flex items-center justify-center">
                      <div class="relative">
                        <svg class="w-20 h-20 transform -rotate-90">
                          <circle
                            cx="40"
                            cy="40"
                            r="36"
                            stroke="currentColor"
                            stroke-width="8"
                            fill="none"
                            class="text-gray-200 dark:text-gray-700"
                          />
                          <circle
                            cx="40"
                            cy="40"
                            r="36"
                            stroke="currentColor"
                            stroke-width="8"
                            fill="none"
                            :stroke-dasharray="`${(payslip.koperasi_summary.eligible_count / payslip.koperasi_summary.total_checked) * 226} 226`"
                            class="text-green-500 transition-all duration-500"
                          />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                          <div class="text-center">
                            <div class="text-lg font-bold">{{ payslip.koperasi_summary.eligible_count }}</div>
                            <div class="text-xs text-muted-foreground">of {{ payslip.koperasi_summary.total_checked }}</div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <p class="text-center text-sm text-muted-foreground">
                      Eligible for {{ payslip.koperasi_summary.eligible_count }} koperasi
                    </p>
                  </div>
                  <div v-else class="text-center text-sm text-muted-foreground py-4">
                    No koperasi data available
                  </div>
                </CardContent>
              </Card>
            </div>

            <!-- Koperasi Detailed Results -->
            <div v-if="payslip.extracted_data?.koperasi_results && Object.keys(payslip.extracted_data.koperasi_results).length > 0">
              <h4 class="text-sm font-medium mb-3 flex items-center gap-2">
                <ListChecks class="h-4 w-4" />
                Koperasi Eligibility Details
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                  v-for="(isEligible, koperasiName) in payslip.extracted_data.koperasi_results"
                  :key="koperasiName"
                  class="flex items-center justify-between p-3 rounded-lg border"
                  :class="isEligible ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'"
                >
                  <div class="flex-1">
                    <div class="font-medium text-sm">{{ koperasiName }}</div>
                    <div class="text-xs text-muted-foreground mt-1">
                      Required: {{ getKoperasiRequirement(String(koperasiName)) }}% • Current: {{ payslip.extracted_data?.peratus_gaji_bersih || 0 }}%
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Badge :variant="isEligible ? 'default' : 'secondary'" class="text-xs">
                      {{ isEligible ? 'Eligible' : 'Not Eligible' }}
                    </Badge>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error Message (if failed) -->
            <div v-if="payslip.status === 'failed' && payslip.processing_error" 
                 class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <div class="flex items-center gap-2 mb-2">
                <AlertCircle class="h-4 w-4 text-red-600" />
                <h5 class="font-medium text-red-800 dark:text-red-200">Processing Error</h5>
              </div>
              <p class="text-sm text-red-700 dark:text-red-300">{{ payslip.processing_error }}</p>
            </div>
          </div>
        </div>
      </Transition>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
// import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FileText, User, Hash, CalendarDays, TrendingUp, TrendingDown, Minus,
  CheckCircle, ChevronRight, MoreVertical, Download, Copy, Trash2,
  RefreshCw, Info, Wallet, Building2, ListChecks, AlertCircle,
  MessageCircle, Send, Globe
} from 'lucide-vue-next'
import { computed } from 'vue'

interface Props {
  payslip: {
    id: number
    name: string
    status: string
    size: number
    source?: string
    created_at: string
    processing_completed_at?: string
    processing_error?: string
    extracted_data?: any
    quality_metrics?: any
    koperasi_summary?: any
  }
  isExpanded: boolean
}

const props = defineProps<Props>()

// Utility functions
const formatCurrency = (amount?: number) => {
  if (!amount) return 'RM 0.00'
  return `RM ${amount.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-MY', { 
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('en-MY', { 
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const formatProcessingTime = (seconds?: number) => {
  if (!seconds) return 'N/A'
  if (seconds < 60) return `${seconds.toFixed(1)}s`
  return `${(seconds / 60).toFixed(1)}m`
}

const formatStatus = (status: string) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const getStatusVariant = (status: string) => {
  const variants: Record<string, any> = {
    completed: 'default',
    processing: 'secondary',
    queued: 'outline',
    failed: 'destructive'
  }
  return variants[status] || 'outline'
}

const getStatusBorderColor = (status: string) => {
  const colors: Record<string, string> = {
    completed: 'border-l-green-500',
    processing: 'border-l-blue-500',
    queued: 'border-l-yellow-500',
    failed: 'border-l-red-500'
  }
  return colors[status] || 'border-l-gray-500'
}

const getSourceIcon = (source?: string) => {
  const icons: Record<string, any> = {
    telegram: MessageCircle,
    whatsapp: Send,
    web: Globe
  }
  return icons[source || ''] || Globe
}

const getSourceBadgeColor = (source?: string) => {
  const colors: Record<string, string> = {
    telegram: 'bg-blue-500',
    whatsapp: 'bg-green-500',
    web: 'bg-purple-500'
  }
  return colors[source || ''] || 'bg-gray-500'
}

const getConfidenceIndicatorColor = (score: number) => {
  if (score >= 80) return 'text-green-500'
  if (score >= 60) return 'text-yellow-500'
  return 'text-red-500'
}

const getKoperasiRequirement = (koperasiName: string): string => {
  // This should match the backend logic
  const requirements: Record<string, string> = {
    'Koperasi Maju Jaya': '80',
    'Koperasi Mudah Lulus': '95',
    'Koperasi Pos Malaysia': '85',
    // Add more as needed
  }
  return requirements[koperasiName] || '85'
}

const copyToClipboard = (payslip: any) => {
  const data = payslip.extracted_data || {}
  const text = `
Payslip Details
--------------
Name: ${data.nama || 'N/A'}
Employee ID: ${data.no_gaji || 'N/A'}
Month: ${data.bulan || 'N/A'}
Net Salary: ${formatCurrency(data.gaji_bersih)}
Status: ${payslip.status}
Processed: ${formatDate(payslip.created_at)}
  `.trim()
  
  navigator.clipboard.writeText(text)
}
</script> 