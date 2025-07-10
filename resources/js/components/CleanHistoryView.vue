<template>
  <div class="space-y-4">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <div class="flex flex-col items-center space-y-2">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-muted border-t-primary"></div>
        <p class="text-sm text-muted-foreground">Loading payslips...</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="payslips.length === 0" class="flex flex-col items-center justify-center py-12">
      <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
        <FileText class="h-8 w-8 text-muted-foreground" />
      </div>
      <h3 class="text-lg font-semibold mb-2">No payslips found</h3>
      <p class="text-muted-foreground text-center max-w-sm">
        Your payslip processing history will appear here once you upload and process your first payslip.
      </p>
    </div>

    <!-- Statistics Cards -->
    <div v-else class="grid gap-4 md:grid-cols-4">
      <div class="rounded-lg border bg-card p-4">
        <div class="flex items-center space-x-2">
          <div class="h-2 w-2 bg-blue-500 rounded-full"></div>
          <p class="text-sm font-medium">Total</p>
        </div>
        <p class="text-2xl font-bold">{{ stats.totalProcessed }}</p>
        <p class="text-xs text-muted-foreground">Payslips Processed</p>
      </div>
      
      <div class="rounded-lg border bg-card p-4">
        <div class="flex items-center space-x-2">
          <div class="h-2 w-2 bg-green-500 rounded-full"></div>
          <p class="text-sm font-medium">Success</p>
        </div>
        <p class="text-2xl font-bold">{{ stats.successRate }}%</p>
        <p class="text-xs text-muted-foreground">Success Rate</p>
      </div>
      
      <div class="rounded-lg border bg-card p-4">
        <div class="flex items-center space-x-2">
          <div class="h-2 w-2 bg-purple-500 rounded-full"></div>
          <p class="text-sm font-medium">Speed</p>
        </div>
        <p class="text-2xl font-bold">{{ stats.avgProcessingTime }}s</p>
        <p class="text-xs text-muted-foreground">Avg Processing Time</p>
      </div>
      
      <div class="rounded-lg border bg-card p-4">
        <div class="flex items-center space-x-2">
          <div class="h-2 w-2 bg-orange-500 rounded-full"></div>
          <p class="text-sm font-medium">Accuracy</p>
        </div>
        <p class="text-2xl font-bold">{{ stats.avgConfidence }}%</p>
        <p class="text-xs text-muted-foreground">Avg Confidence Score</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4 p-4 border rounded-lg bg-card">
      <div class="flex-1">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            placeholder="Search by name, employee ID, or month..."
            class="pl-9"
          />
        </div>
      </div>
      
      <div class="flex gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm">
              <Filter class="h-4 w-4 mr-2" />
              {{ activeFilters.status || 'Status' }}
              <Badge v-if="activeFilters.status" variant="secondary" class="ml-2 h-4 px-1 text-xs">
                1
              </Badge>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="setFilter('status', '')">
              All
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'completed')">
              Completed
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'failed')">
              Failed
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'processing')">
              Processing
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'queued')">
              Queued
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm">
              <Calendar class="h-4 w-4 mr-2" />
              {{ getDateRangeLabel() }}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-40">
            <DropdownMenuLabel>Date Range</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="setFilter('dateRange', 'week')">
              This Week
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'month')">
              This Month
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'quarter')">
              This Quarter
            </DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'year')">
              This Year
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        
        <Button variant="outline" size="sm" @click="clearFilters" v-if="hasActiveFilters">
          <X class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <!-- Payslip List -->
    <div class="space-y-2">
      <div
        v-for="payslip in paginatedPayslips"
        :key="payslip.id"
        class="border rounded-lg bg-card"
      >
        <div 
          class="flex items-center justify-between p-4 cursor-pointer hover:bg-muted/50 transition-colors"
          @click="toggleExpanded(payslip.id)"
        >
          <div class="flex items-center space-x-3 flex-1">
            <!-- File Icon -->
            <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center">
              <FileText class="h-5 w-5 text-muted-foreground" />
            </div>
            
            <div class="flex-1 min-w-0">
              <div class="flex items-center space-x-2">
                <p class="text-sm font-medium truncate">{{ payslip.name }}</p>
                
                <!-- Status indicator -->
                <div class="flex items-center space-x-1">
                  <div :class="getStatusIndicatorClasses(payslip.status)" class="w-2 h-2 rounded-full"></div>
                  <span :class="getStatusTextClasses(payslip.status)" class="text-xs font-medium">
                    {{ getStatusLabel(payslip.status) }}
                  </span>
                </div>
                
                <!-- Source indicator -->
                <div class="flex items-center space-x-1">
                  <component :is="getSourceIcon(payslip.source)" class="w-3 h-3 text-muted-foreground" />
                  <span class="text-xs text-muted-foreground font-medium">
                    {{ getSourceLabel(payslip.source) }}
                  </span>
                </div>
              </div>
              
              <div class="flex items-center space-x-4 text-xs text-muted-foreground mt-1">
                <span>{{ payslip.data?.nama || 'Unknown Employee' }}</span>
                <span>•</span>
                <span>{{ payslip.data?.bulan || formatDate(payslip.created_at) }}</span>
                <span>•</span>
                <span>{{ formatCurrency(payslip.data?.gaji_bersih) }}</span>
              </div>
            </div>
          </div>
          
          <div class="flex items-center space-x-2">
            <div class="text-right">
              <p class="text-sm font-medium">{{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%</p>
              <p class="text-xs text-muted-foreground">Confidence</p>
            </div>
            <ChevronDown 
              :class="['h-4 w-4 transition-transform', expandedItems.has(payslip.id) && 'rotate-180']"
            />
          </div>
        </div>
        
        <!-- Expanded Details -->
        <div v-if="expandedItems.has(payslip.id)" class="border-t bg-muted/20 p-4">
          <div class="grid gap-4 md:grid-cols-2">
            <!-- Basic Info -->
            <div class="space-y-3">
              <h4 class="text-sm font-medium">Basic Information</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Employee Name</span>
                  <span>{{ payslip.data?.nama || 'N/A' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Employee ID</span>
                  <span>{{ payslip.data?.no_gaji || 'N/A' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Month/Year</span>
                  <span>{{ payslip.data?.bulan || 'N/A' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Processing Time</span>
                  <span>{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</span>
                </div>
              </div>
            </div>
            
            <!-- Salary Details -->
            <div class="space-y-3">
              <h4 class="text-sm font-medium">Salary Breakdown</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Basic Salary</span>
                  <span>{{ formatCurrency(payslip.data?.gaji_pokok) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Total Earnings</span>
                  <span>{{ formatCurrency(payslip.data?.jumlah_pendapatan) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-muted-foreground">Total Deductions</span>
                  <span>{{ formatCurrency(payslip.data?.jumlah_potongan) }}</span>
                </div>
                <div class="flex justify-between text-sm font-medium">
                  <span>Net Salary</span>
                  <span>{{ formatCurrency(payslip.data?.gaji_bersih) }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Koperasi Results -->
          <div v-if="payslip.data?.koperasi_results && Object.keys(payslip.data.koperasi_results).length > 0" class="mt-4 pt-4 border-t">
            <h4 class="text-sm font-medium mb-3">Koperasi Eligibility</h4>
            <div class="grid gap-2 md:grid-cols-2">
              <div
                v-for="(isEligible, koperasiName) in payslip.data.koperasi_results"
                :key="koperasiName"
                class="flex items-center justify-between p-2 rounded border text-sm"
              >
                <span class="font-medium">{{ koperasiName }}</span>
                <Badge :variant="isEligible ? 'default' : 'secondary'" class="text-xs">
                  {{ isEligible ? '✓ Eligible' : '✗ Not Eligible' }}
                </Badge>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="flex justify-end space-x-2 mt-4 pt-4 border-t">
            <Button variant="outline" size="sm" @click="handleView(payslip)">
              <Eye class="h-4 w-4 mr-1" />
              View
            </Button>
            <Button variant="outline" size="sm" @click="handleDownload(payslip)">
              <Download class="h-4 w-4 mr-1" />
              Download
            </Button>
            <Button variant="outline" size="sm" @click="handleDelete(payslip)" class="text-destructive">
              <Trash2 class="h-4 w-4 mr-1" />
              Delete
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex items-center justify-between">
      <p class="text-sm text-muted-foreground">
        Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + itemsPerPage, filteredPayslips.length) }} of {{ filteredPayslips.length }} payslips
      </p>
      
      <div class="flex items-center space-x-2">
        <Button
          variant="outline"
          size="sm"
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
        >
          <ChevronLeft class="h-4 w-4" />
        </Button>
        
        <div class="flex items-center space-x-1">
          <Button
            v-for="page in visiblePageNumbers"
            :key="page"
            variant="outline"
            size="sm"
            @click="goToPage(page)"
            :class="{ 'bg-primary text-primary-foreground': page === currentPage }"
          >
            {{ page }}
          </Button>
        </div>
        
        <Button
          variant="outline"
          size="sm"
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
        >
          <ChevronRight class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { 
  FileText, 
  Search, 
  X, 
  ChevronDown, 
  ChevronLeft, 
  ChevronRight,
  Eye, 
  Download, 
  Trash2,
  Filter,
  Calendar,
  CheckCircle, // Added for completed status
  XCircle, // Added for failed status
  Loader2, // Added for processing status
  Clock, // Added for queued status
  MessageSquare, // Added for Telegram
  Globe // Added for Web App
} from 'lucide-vue-next'

interface PayslipData {
  nama?: string
  no_gaji?: string
  bulan?: string
  gaji_pokok?: number
  jumlah_pendapatan?: number
  jumlah_potongan?: number
  gaji_bersih?: number
  peratus_gaji_bersih?: number
  koperasi_results?: Record<string, boolean>
}

interface Payslip {
  id: number
  name: string
  status: 'completed' | 'failed' | 'processing' | 'queued'
  size: number
  source?: string
  created_at: string
  data?: PayslipData
  quality_metrics?: {
    confidence_score?: number
    data_completeness?: number
    processing_time?: number
  }
  koperasi_summary?: {
    total_checked?: number
    eligible_count?: number
  }
}

interface Statistics {
  totalProcessed: number
  successRate: number
  avgProcessingTime: number
  avgConfidence: number
}

// State
const payslips = ref<Payslip[]>([])
const stats = ref<Statistics>({
  totalProcessed: 0,
  successRate: 0,
  avgProcessingTime: 0,
  avgConfidence: 0
})
const isLoading = ref(false)
const searchQuery = ref('')
const expandedItems = ref<Set<number>>(new Set())
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Filters
const activeFilters = ref<{[key: string]: string}>({
  status: '',
  dateRange: 'month'
})

// Computed
const hasActiveFilters = computed(() => {
  return searchQuery.value || activeFilters.value.status || activeFilters.value.dateRange !== 'month'
})

const filteredPayslips = computed(() => {
  let filtered = [...payslips.value]

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(query) ||
      p.data?.nama?.toLowerCase().includes(query) ||
      p.data?.no_gaji?.toLowerCase().includes(query) ||
      p.data?.bulan?.toLowerCase().includes(query)
    )
  }

  // Status filter
  if (activeFilters.value.status) {
    filtered = filtered.filter(p => p.status === activeFilters.value.status)
  }

  // Date range filter
  if (activeFilters.value.dateRange !== 'month') {
    const now = new Date()
    const ranges: Record<string, Date> = {
      week: new Date(now.setDate(now.getDate() - 7)),
      quarter: new Date(now.setMonth(now.getMonth() - 3)),
      year: new Date(now.setFullYear(now.getFullYear() - 1))
    }
    
    const startDate = ranges[activeFilters.value.dateRange]
    if (startDate) {
      filtered = filtered.filter(p => new Date(p.created_at) >= startDate)
    }
  }

  return filtered
})

const totalPages = computed(() => Math.ceil(filteredPayslips.value.length / itemsPerPage.value))

const paginatedPayslips = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredPayslips.value.slice(start, end)
})

const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value)

const visiblePageNumbers = computed(() => {
  const total = totalPages.value
  const current = currentPage.value
  const delta = 2
  const range: number[] = []

  for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
    range.push(i)
  }

  return range
})

// Methods
const loadPayslips = async () => {
  isLoading.value = true
  try {
    const response = await fetch('/api/payslips', {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('API Response:', data)
    
    // Handle the API response structure correctly
    let payslipsData = []
    if (data.payslips) {
      payslipsData = data.payslips
    } else if (data.data) {
      payslipsData = data.data
    } else if (Array.isArray(data)) {
      payslipsData = data
    }
    
    // Transform the data to match our interface
    payslips.value = payslipsData.map((p: any) => ({
      id: p.id,
      name: p.name || (p.file_path ? p.file_path.split('/').pop() : 'Unknown File'),
      status: p.status,
      size: p.size || 0,
      source: p.source,
      created_at: p.created_at,
      data: p.data || p.extracted_data, // Handle both data structures
      quality_metrics: p.quality_metrics,
      koperasi_summary: p.koperasi_summary
    }))
    
    console.log('Processed payslips:', payslips.value)
    
    // Calculate statistics
    calculateStatistics()
  } catch (error) {
    console.error('Failed to load payslips:', error)
    payslips.value = []
  } finally {
    isLoading.value = false
  }
}

const calculateStatistics = () => {
  const total = payslips.value.length
  const completed = payslips.value.filter(p => p.status === 'completed').length
  const totalProcessingTime = payslips.value.reduce((sum, p) => sum + (p.quality_metrics?.processing_time || 0), 0)
  const totalConfidence = payslips.value.reduce((sum, p) => sum + (p.quality_metrics?.confidence_score || 0), 0)
  
  stats.value = {
    totalProcessed: total,
    successRate: total > 0 ? Math.round((completed / total) * 100) : 0,
    avgProcessingTime: total > 0 ? Math.round(totalProcessingTime / total) : 0,
    avgConfidence: total > 0 ? Math.round(totalConfidence / total) : 0
  }
}

const toggleExpanded = (id: number) => {
  if (expandedItems.value.has(id)) {
    expandedItems.value.delete(id)
  } else {
    expandedItems.value.add(id)
  }
}

const setFilter = (key: string, value: string) => {
  activeFilters.value[key] = value
  currentPage.value = 1 // Reset to first page when filter changes
}

const getDateRangeLabel = () => {
  switch (activeFilters.value.dateRange) {
    case 'week': return 'This Week'
    case 'month': return 'This Month'
    case 'quarter': return 'This Quarter'
    case 'year': return 'This Year'
    default: return 'Period'
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  activeFilters.value = {
    status: '',
    dateRange: 'month'
  }
  currentPage.value = 1
}

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const getSourceLabel = (source?: string) => {
  if (!source) return 'Unknown Source'
  if (source.includes('telegram')) return 'Telegram'
  return 'Web App'
}

const getSourceIcon = (source?: string) => {
  if (!source) return FileText
  if (source.includes('telegram')) return MessageSquare
  return Globe
}

const getStatusLabel = (status: string) => {
  switch (status) {
    case 'completed': return 'Completed'
    case 'failed': return 'Failed'
    case 'processing': return 'Processing'
    case 'queued': return 'Queued'
    default: return 'Unknown'
  }
}

const getStatusIndicatorClasses = (status: string) => {
  switch (status) {
    case 'completed': return 'bg-green-500'
    case 'failed': return 'bg-red-500'
    case 'processing': return 'bg-blue-500'
    case 'queued': return 'bg-gray-500'
    default: return 'bg-gray-500'
  }
}

const getStatusTextClasses = (status: string) => {
  switch (status) {
    case 'completed': return 'text-green-600'
    case 'failed': return 'text-red-600'
    case 'processing': return 'text-blue-600'
    case 'queued': return 'text-gray-600'
    default: return 'text-gray-600'
  }
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString('en-MY', { 
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const formatCurrency = (amount?: number): string => {
  if (!amount) return 'RM 0.00'
  return `RM ${amount.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const formatProcessingTime = (seconds?: number): string => {
  if (!seconds) return 'N/A'
  if (seconds < 60) return `${seconds.toFixed(1)}s`
  if (seconds < 3600) return `${(seconds / 60).toFixed(1)}m`
  return `${(seconds / 3600).toFixed(1)}h`
}

const handleView = (payslip: Payslip) => {
  console.log('View payslip:', payslip.id)
}

const handleDownload = (payslip: Payslip) => {
  console.log('Download payslip:', payslip.id)
}

const handleDelete = async (payslip: Payslip) => {
  if (confirm('Are you sure you want to delete this payslip?')) {
    try {
      const response = await fetch(`/api/payslips/${payslip.id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Content-Type': 'application/json',
        }
      })
      if (response.ok) {
        await loadPayslips()
      }
    } catch (error) {
      console.error('Failed to delete payslip:', error)
    }
  }
}

// Expose loadPayslips for parent component
defineExpose({ loadPayslips })

// Load data on mount
onMounted(() => {
  loadPayslips()
})
</script> 