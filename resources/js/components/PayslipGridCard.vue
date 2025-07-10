<template>
  <Card class="group hover:shadow-lg transition-all duration-300 overflow-hidden relative">
    <!-- Status Ribbon -->
    <div 
      class="absolute top-0 right-0 px-3 py-1 text-xs font-medium text-white rounded-bl-lg"
      :class="getStatusColor(payslip.status)"
    >
      {{ formatStatus(payslip.status) }}
    </div>

    <CardContent class="p-6">
      <!-- Header -->
      <div class="flex items-start justify-between mb-4">
        <div class="flex items-center gap-3">
          <div class="relative">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl flex items-center justify-center">
              <FileText class="h-7 w-7 text-blue-600 dark:text-blue-400" />
            </div>
            <div v-if="payslip.source" 
                 class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center shadow-sm"
                 :class="getSourceBadgeColor(payslip.source)">
              <component :is="getSourceIcon(payslip.source)" class="h-3.5 w-3.5 text-white" />
            </div>
          </div>
          
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-base truncate mb-1">{{ payslip.name }}</h3>
            <p class="text-xs text-muted-foreground">{{ formatFileSize(payslip.size) }}</p>
          </div>
        </div>
      </div>

      <!-- Employee Info -->
      <div class="space-y-2 mb-4">
        <div class="flex items-center gap-2 text-sm">
          <User class="h-4 w-4 text-muted-foreground" />
          <span class="font-medium">{{ payslip.extracted_data?.nama || 'Unknown Employee' }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <Hash class="h-4 w-4 text-muted-foreground" />
          <span>{{ payslip.extracted_data?.no_gaji || 'No ID' }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <CalendarDays class="h-4 w-4 text-muted-foreground" />
          <span>{{ payslip.extracted_data?.bulan || formatDate(payslip.created_at) }}</span>
        </div>
      </div>

      <!-- Metrics -->
      <div class="grid grid-cols-2 gap-3 mb-4">
        <!-- Net Salary -->
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
          <div class="flex items-center justify-between mb-1">
            <Wallet class="h-4 w-4 text-green-600 dark:text-green-400" />
            <span class="text-xs text-green-600 dark:text-green-400">Net</span>
          </div>
          <p class="text-lg font-bold text-green-700 dark:text-green-300">
            {{ formatShortCurrency(payslip.extracted_data?.gaji_bersih) }}
          </p>
        </div>

        <!-- Confidence Score -->
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
          <div class="flex items-center justify-between mb-1">
            <Target class="h-4 w-4 text-blue-600 dark:text-blue-400" />
            <span class="text-xs text-blue-600 dark:text-blue-400">Score</span>
          </div>
          <div class="flex items-center gap-1">
            <p class="text-lg font-bold text-blue-700 dark:text-blue-300">
              {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
            </p>
            <component 
              :is="getConfidenceIcon(payslip.quality_metrics?.confidence_score || 0)"
              :class="['h-4 w-4', getConfidenceIconColor(payslip.quality_metrics?.confidence_score || 0)]"
            />
          </div>
        </div>
      </div>

      <!-- Koperasi Summary -->
      <div v-if="payslip.koperasi_summary && payslip.koperasi_summary.total_checked > 0" class="mb-4">
        <div class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
          <div class="flex items-center gap-2">
            <Building2 class="h-4 w-4 text-muted-foreground" />
            <span class="text-sm font-medium">Koperasi Eligible</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="flex items-center gap-1">
              <span class="text-lg font-bold text-green-600">{{ payslip.koperasi_summary.eligible_count }}</span>
              <span class="text-sm text-muted-foreground">/ {{ payslip.koperasi_summary.total_checked }}</span>
            </div>
            <div class="w-12 h-12">
              <svg class="w-full h-full transform -rotate-90">
                <circle
                  cx="24"
                  cy="24"
                  r="20"
                  stroke="currentColor"
                  stroke-width="4"
                  fill="none"
                  class="text-gray-200 dark:text-gray-700"
                />
                <circle
                  cx="24"
                  cy="24"
                  r="20"
                  stroke="currentColor"
                  stroke-width="4"
                  fill="none"
                  :stroke-dasharray="`${(payslip.koperasi_summary.eligible_count / payslip.koperasi_summary.total_checked) * 126} 126`"
                  class="text-green-500 transition-all duration-500"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Processing Info -->
      <div class="flex items-center justify-between text-xs text-muted-foreground mb-4">
        <span class="flex items-center gap-1">
          <Clock class="h-3 w-3" />
          {{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}
        </span>
        <span>{{ formatRelativeTime(payslip.created_at) }}</span>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <Button
          variant="outline"
          size="sm"
          @click="$emit('view', payslip)"
          class="flex-1"
        >
          <Eye class="h-4 w-4 mr-2" />
          View Details
        </Button>
        
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" class="h-9 w-9">
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
            <DropdownMenuItem v-if="payslip.status === 'failed'" @click="$emit('reprocess', payslip)">
              <RefreshCw class="h-4 w-4 mr-2" />
              Reprocess
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="$emit('delete', payslip)" class="text-red-600">
              <Trash2 class="h-4 w-4 mr-2" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- Error Indicator -->
      <div v-if="payslip.status === 'failed' && payslip.processing_error" 
           class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
        <p class="text-xs text-red-600 dark:text-red-400 line-clamp-2">
          <AlertCircle class="h-3 w-3 inline mr-1" />
          {{ payslip.processing_error }}
        </p>
      </div>
    </CardContent>

    <!-- Hover Effect Overlay -->
    <div class="absolute inset-0 bg-gradient-to-t from-black/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
  </Card>
</template>

<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FileText, User, Hash, CalendarDays, Wallet, Target, Building2,
  Clock, Eye, MoreVertical, Download, Copy, Trash2, RefreshCw,
  AlertCircle, TrendingUp, TrendingDown, Minus, MessageCircle,
  Send, Globe, ChevronUp, ChevronDown
} from 'lucide-vue-next'

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
}

const props = defineProps<Props>()

// Utility functions
const formatShortCurrency = (amount?: number) => {
  if (!amount) return 'RM 0'
  if (amount >= 1000) {
    return `RM ${(amount / 1000).toFixed(1)}k`
  }
  return `RM ${amount.toLocaleString('en-MY')}`
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-MY', { 
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const formatRelativeTime = (date: string) => {
  const now = new Date()
  const then = new Date(date)
  const diffInSeconds = (now.getTime() - then.getTime()) / 1000
  
  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`
  
  return formatDate(date)
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

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    completed: 'bg-green-500',
    processing: 'bg-blue-500',
    queued: 'bg-yellow-500',
    failed: 'bg-red-500'
  }
  return colors[status] || 'bg-gray-500'
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

const getConfidenceIcon = (score: number) => {
  if (score >= 80) return ChevronUp
  if (score >= 60) return Minus
  return ChevronDown
}

const getConfidenceIconColor = (score: number) => {
  if (score >= 80) return 'text-green-500'
  if (score >= 60) return 'text-yellow-500'
  return 'text-red-500'
}

const copyToClipboard = (payslip: any) => {
  const data = payslip.extracted_data || {}
  const text = `
Payslip Details
--------------
Name: ${data.nama || 'N/A'}
Employee ID: ${data.no_gaji || 'N/A'}
Month: ${data.bulan || 'N/A'}
Net Salary: RM ${data.gaji_bersih?.toLocaleString('en-MY') || '0'}
Status: ${payslip.status}
Processed: ${formatDate(payslip.created_at)}
  `.trim()
  
  navigator.clipboard.writeText(text)
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style> 