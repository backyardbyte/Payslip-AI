<template>
  <div class="space-y-4">
    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
      <Card class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
        <CardContent class="p-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-100 text-xs">Total Processed</p>
              <p class="text-xl font-bold">{{ analytics.total_processed || 0 }}</p>
            </div>
            <FileText class="h-6 w-6 text-blue-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-green-500 to-green-600 text-white">
        <CardContent class="p-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-green-100 text-xs">Success Rate</p>
              <p class="text-xl font-bold">{{ analytics.success_rate || 0 }}%</p>
            </div>
            <CheckCircle class="h-6 w-6 text-green-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-purple-500 to-purple-600 text-white">
        <CardContent class="p-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-purple-100 text-xs">Avg Processing</p>
              <p class="text-xl font-bold">{{ formatProcessingTime(analytics.average_processing_time || 0) }}</p>
            </div>
            <Clock class="h-6 w-6 text-purple-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-orange-500 to-orange-600 text-white">
        <CardContent class="p-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-orange-100 text-xs">High Confidence</p>
              <p class="text-xl font-bold">{{ analytics.quality_metrics?.high_confidence || 0 }}</p>
            </div>
            <TrendingUp class="h-6 w-6 text-orange-200" />
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filters -->
    <Card>
      <CardHeader class="pb-3">
        <CardTitle class="flex items-center gap-2 text-base">
          <Filter class="h-4 w-4" />
          Filters & Search
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div class="space-y-1">
            <Label class="text-xs font-medium">Search</Label>
            <div class="relative">
              <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
              <Input
                v-model="filters.search"
                placeholder="Search files..."
                class="pl-8 h-8 text-sm"
              />
            </div>
          </div>
          
          <div class="space-y-1">
            <Label class="text-xs font-medium">Status</Label>
            <select 
              v-model="filters.status"
              class="w-full px-2.5 py-1.5 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="">All statuses</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="processing">Processing</option>
              <option value="queued">Queued</option>
            </select>
          </div>
          
          <div class="space-y-1">
            <Label class="text-xs font-medium">Date Range</Label>
            <select 
              v-model="filters.dateRange"
              class="w-full px-2.5 py-1.5 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
              <option value="all">All Time</option>
            </select>
          </div>
          
          <div class="space-y-1">
            <Label class="text-xs font-medium">Sort By</Label>
            <select 
              v-model="filters.sortBy"
              class="w-full px-2.5 py-1.5 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="created_at">Date Created</option>
              <option value="processing_completed_at">Date Completed</option>
              <option value="confidence_score">Confidence</option>
            </select>
          </div>
        </div>
        
        <div class="flex items-center justify-between pt-2">
          <Button variant="outline" size="sm" @click="clearFilters" class="h-7 text-xs">
            <X class="h-3 w-3 mr-1.5" />
            Clear Filters
          </Button>
          <Button variant="outline" size="sm" @click="refreshData" class="h-7 text-xs">
            <RefreshCw class="h-3 w-3 mr-1.5" :class="{ 'animate-spin': isRefreshing }" />
            Refresh
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Payslips Accordion -->
    <Card>
      <CardHeader class="pb-3">
        <CardTitle class="flex items-center gap-2 text-base">
          <List class="h-4 w-4" />
          Processing History ({{ filteredPayslips.length }})
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="isLoading" class="flex items-center justify-center p-6">
          <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
          <span class="ml-2 text-sm text-muted-foreground">Loading history...</span>
        </div>
        
        <div v-else-if="filteredPayslips.length === 0" class="text-center p-6">
          <FileText class="h-8 w-8 text-muted-foreground mx-auto mb-3" />
          <h3 class="text-sm font-medium">No payslips found</h3>
          <p class="text-muted-foreground text-xs mt-1">Try adjusting your filters</p>
        </div>
        
        <div v-else class="space-y-1">
          <div 
            v-for="payslip in paginatedPayslips" 
            :key="payslip.id"
            class="border rounded-md overflow-hidden"
          >
            <!-- Accordion Header -->
            <div 
              class="flex items-center justify-between p-3 hover:bg-muted/30 transition-colors cursor-pointer"
              @click="toggleExpanded(payslip.id)"
            >
              <div class="flex items-center space-x-3 flex-1">
                <div class="w-8 h-8 bg-muted rounded-md flex items-center justify-center">
                  <FileText class="h-4 w-4 text-muted-foreground" />
                </div>
                
                <div class="flex-1 min-w-0">
                  <p class="font-medium truncate text-sm">{{ payslip.name }}</p>
                  <div class="flex items-center space-x-1.5 text-xs text-muted-foreground">
                    <span>{{ formatFileSize(payslip.size) }}</span>
                    <span>•</span>
                    <span>{{ formatDate(payslip.created_at) }}</span>
                    <span>•</span>
                    <Badge :class="getSourceClasses(payslip.source)" class="text-xs px-1.5 py-0.5">
                      {{ getSourceLabel(payslip.source) }}
                    </Badge>
                    <span v-if="payslip.data?.nama">•</span>
                    <span v-if="payslip.data?.nama" class="truncate max-w-32">{{ payslip.data.nama }}</span>
                  </div>
                </div>
              </div>
              
              <div class="flex items-center space-x-3">
                <Badge :variant="getStatusVariant(payslip.status)" class="text-xs px-2 py-0.5">
                  {{ payslip.status }}
                </Badge>
                
                <div class="text-center">
                  <div class="text-xs font-medium">
                    {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
                  </div>
                  <div class="text-xs text-muted-foreground">confidence</div>
                </div>
                
                <div class="text-center">
                  <div class="text-xs font-medium">
                    {{ formatProcessingTime(payslip.quality_metrics?.processing_time || 0) }}
                  </div>
                  <div class="text-xs text-muted-foreground">time</div>
                </div>

                <!-- Koperasi Status Summary -->
                <div v-if="payslip.koperasi_summary" class="text-center">
                  <div class="text-xs font-medium text-green-600">
                    {{ payslip.koperasi_summary.eligible_count || 0 }}/{{ payslip.koperasi_summary.total_checked || 0 }}
                  </div>
                  <div class="text-xs text-muted-foreground">eligible</div>
                </div>
                
                <div class="flex items-center space-x-1">
                  <Button 
                    variant="ghost" 
                    size="icon"
                    @click.stop="reprocessPayslip(payslip)"
                    v-if="payslip.status === 'failed'"
                    class="h-6 w-6"
                  >
                    <RefreshCw class="h-3 w-3" />
                  </Button>
                  <Button 
                    variant="ghost" 
                    size="icon"
                    @click.stop="deletePayslip(payslip)"
                    class="h-6 w-6 text-red-600 hover:text-red-700"
                  >
                    <Trash2 class="h-3 w-3" />
                  </Button>
                  <ChevronDown 
                    class="h-3 w-3 text-muted-foreground transition-transform duration-200"
                    :class="{ 'rotate-180': expandedPayslips.has(payslip.id) }"
                  />
                </div>
              </div>
            </div>
            
            <!-- Accordion Content -->
            <div v-if="expandedPayslips.has(payslip.id)" class="border-t bg-muted/10">
              <div class="p-4 space-y-4">
                <!-- Quality Metrics -->
                <div class="grid grid-cols-3 gap-3">
                  <div class="text-center p-3 bg-background rounded border">
                    <div class="text-lg font-bold text-blue-600">
                      {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
                    </div>
                    <div class="text-xs text-muted-foreground">Confidence</div>
                  </div>
                  
                  <div class="text-center p-3 bg-background rounded border">
                    <div class="text-lg font-bold text-green-600">
                      {{ Math.round(payslip.quality_metrics?.data_completeness || 0) }}%
                    </div>
                    <div class="text-xs text-muted-foreground">Completeness</div>
                  </div>
                  
                  <div class="text-center p-3 bg-background rounded border">
                    <div class="text-lg font-bold text-purple-600">
                      {{ formatProcessingTime(payslip.quality_metrics?.processing_time || 0) }}
                    </div>
                    <div class="text-xs text-muted-foreground">Processing Time</div>
                  </div>
                </div>
                
                <!-- Extracted Data -->
                <div class="bg-background rounded border p-3">
                  <h4 class="font-medium mb-3 text-sm">Extracted Data</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Employee Name</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ payslip.data?.nama || 'Not found' }}</p>
                      </div>
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Employee Number</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ payslip.data?.no_gaji || 'Not found' }}</p>
                      </div>
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Month/Year</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ payslip.data?.bulan || 'Not found' }}</p>
                      </div>
                    </div>
                    <div class="space-y-2">
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Basic Salary</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ formatCurrency(payslip.data?.gaji_pokok) }}</p>
                      </div>
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Net Salary</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ formatCurrency(payslip.data?.gaji_bersih) }}</p>
                      </div>
                      <div>
                        <Label class="text-xs font-medium text-muted-foreground">Salary Percentage</Label>
                        <p class="mt-0.5 text-sm font-medium">{{ payslip.data?.peratus_gaji_bersih || 'Not found' }}%</p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Koperasi Eligibility -->
                <div v-if="payslip.koperasi_summary" class="bg-background rounded border p-3">
                  <h4 class="font-medium mb-3 text-sm">Koperasi Eligibility</h4>
                  
                  <!-- Summary Stats -->
                  <div class="mb-3 p-2 bg-muted/50 rounded text-center">
                    <div class="text-lg font-bold text-green-600">
                      {{ payslip.koperasi_summary.eligible_count || 0 }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                      out of {{ payslip.koperasi_summary.total_checked || 0 }} koperasi are eligible
                    </div>
                  </div>

                  <!-- Koperasi List -->
                  <div class="space-y-1">
                    <div 
                      v-for="(isEligible, koperasiName) in payslip.data?.koperasi_results || {}"
                      :key="koperasiName"
                      class="flex items-center justify-between p-2 border rounded text-xs"
                    >
                      <div class="flex-1">
                        <span class="font-medium">{{ koperasiName }}</span>
                        <div class="text-muted-foreground">
                          Requirement: {{ getKoperasiRequirement(koperasiName) }}% • 
                          Current: {{ payslip.data?.peratus_gaji_bersih || 0 }}%
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <Badge :variant="isEligible ? 'default' : 'secondary'" class="text-xs px-1.5 py-0.5">
                          {{ isEligible ? 'Eligible' : 'Not Eligible' }}
                        </Badge>
                        <CheckCircle v-if="isEligible" class="h-3 w-3 text-green-600" />
                        <X v-else class="h-3 w-3 text-red-600" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Pagination -->
          <div v-if="totalPages > 1" class="flex items-center justify-center pt-3 space-x-2">
            <Button 
              variant="outline" 
              size="sm" 
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="h-7 text-xs"
            >
              <ChevronLeft class="h-3 w-3" />
            </Button>
            
            <span class="text-xs text-muted-foreground">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            
            <Button 
              variant="outline" 
              size="sm" 
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="h-7 text-xs"
            >
              <ChevronRight class="h-3 w-3" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { 
  FileText, CheckCircle, Clock, TrendingUp, Filter, Search, X, RefreshCw,
  List, LoaderCircle, Trash2, ChevronLeft, ChevronRight, ChevronDown
} from 'lucide-vue-next'

interface Payslip {
  id: number
  name: string
  status: string
  size: number
  source?: string
  created_at: string
  data?: {
    nama?: string
    no_gaji?: string
    bulan?: string
    gaji_pokok?: number
    jumlah_pendapatan?: number
    jumlah_potongan?: number
    gaji_bersih?: number
    peratus_gaji_bersih?: number
    koperasi_results?: Record<string, boolean>
    error?: string
  }
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

interface Analytics {
  total_processed: number
  success_rate: number
  average_processing_time: number
  quality_metrics: {
    high_confidence: number
    medium_confidence: number
    low_confidence: number
  }
}

// State
const payslips = ref<Payslip[]>([])
const analytics = ref<Analytics>({} as Analytics)
const isLoading = ref(false)
const isRefreshing = ref(false)
const expandedPayslips = ref<Set<number>>(new Set())

// Filters
const filters = ref({
  search: '',
  status: '',
  dateRange: 'month',
  sortBy: 'created_at'
})

// Pagination
const currentPage = ref(1)
const perPage = ref(10)

// Computed
const filteredPayslips = computed(() => {
  let filtered = [...payslips.value]
  
  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(search) ||
      p.data?.nama?.toLowerCase().includes(search)
    )
  }
  
  if (filters.value.status) {
    filtered = filtered.filter(p => p.status === filters.value.status)
  }
  
  // Sort
  filtered.sort((a, b) => {
    const aValue = a.created_at
    const bValue = b.created_at
    return new Date(bValue).getTime() - new Date(aValue).getTime()
  })
  
  return filtered
})

const totalPages = computed(() => Math.ceil(filteredPayslips.value.length / perPage.value))

const paginatedPayslips = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return filteredPayslips.value.slice(start, end)
})

// Methods
const toggleExpanded = (payslipId: number) => {
  if (expandedPayslips.value.has(payslipId)) {
    expandedPayslips.value.delete(payslipId)
  } else {
    expandedPayslips.value.add(payslipId)
  }
}

const getKoperasiRequirement = (koperasiName: string): string => {
  // Common koperasi requirements mapping
  const requirements: Record<string, string> = {
    'Koperasi Maju Jaya': '80',
    'Koperasi Mudah Lulus': '95',
    'Koperasi Pos Malaysia': '85',
    'Koperasi Guru Malaysia': '75',
    'Koperasi Swasta Berhad': '85',
    'Koperasi Polis Malaysia': '80',
    'Koperasi Cergas Malaysia': '82',
    'Koperasi Petronas Berhad': '88',
    'Koperasi Rakyat Malaysia': '90',
    'Koperasi Tenaga Nasional': '83',
    'Koperasi Pekerja Kerajaan': '78',
    'Koperasi Sejahtera Berhad': '80',
    'Koperasi Telekom Malaysia': '85',
    'Koperasi Kesihatan Malaysia': '82',
    'Koperasi Bank Islam Malaysia': '87'
  }
  
  return requirements[koperasiName] || '85'
}

const loadPayslips = async () => {
  isLoading.value = true
  try {
    const response = await fetch('/api/payslips', {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      }
    })
    const data = await response.json()
    // Handle the API response structure correctly
    payslips.value = data.payslips || data.data || data || []
  } catch (error) {
    console.error('Failed to load payslips:', error)
    payslips.value = []
  } finally {
    isLoading.value = false
  }
}

const loadAnalytics = async () => {
  try {
    const response = await fetch('/api/payslips/analytics', {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      }
    })
    if (response.ok) {
      analytics.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to load analytics:', error)
  }
}

const clearFilters = () => {
  filters.value = {
    search: '',
    status: '',
    dateRange: 'month',
    sortBy: 'created_at'
  }
  currentPage.value = 1
}

const refreshData = () => {
  isRefreshing.value = true
  Promise.all([loadPayslips(), loadAnalytics()]).finally(() => {
    isRefreshing.value = false
  })
}

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const reprocessPayslip = async (payslip: Payslip) => {
  try {
    const response = await fetch(`/api/payslips/${payslip.id}/reprocess`, { 
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      }
    })
    if (response.ok) {
      loadPayslips()
    }
  } catch (error) {
    console.error('Failed to reprocess payslip:', error)
  }
}

const deletePayslip = async (payslip: Payslip) => {
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
        loadPayslips()
      }
    } catch (error) {
      console.error('Failed to delete payslip:', error)
    }
  }
}

// Utility functions
const formatFileSize = (bytes: number): string => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString()
}

const formatProcessingTime = (seconds: number): string => {
  if (seconds < 60) return `${seconds.toFixed(1)}s`
  if (seconds < 3600) return `${(seconds / 60).toFixed(1)}m`
  return `${(seconds / 3600).toFixed(1)}h`
}

const formatCurrency = (amount: number | null | undefined): string => {
  if (!amount) return 'RM 0.00'
  return `RM ${amount.toLocaleString('en-MY', { minimumFractionDigits: 2 })}`
}

const getStatusVariant = (status: string) => {
  switch (status) {
    case 'completed': return 'default'
    case 'failed': return 'destructive'
    case 'processing': return 'secondary'
    case 'queued': return 'outline'
    default: return 'outline'
  }
}

const getSourceClasses = (source: string | undefined) => {
  switch (source) {
    case 'telegram': return 'bg-blue-100 text-blue-800 border-blue-200 hover:bg-blue-200'
    case 'whatsapp': return 'bg-green-100 text-green-800 border-green-200 hover:bg-green-200'
    case 'web': return 'bg-purple-100 text-purple-800 border-purple-200 hover:bg-purple-200'
    default: return 'bg-purple-100 text-purple-800 border-purple-200 hover:bg-purple-200'
  }
}

const getSourceLabel = (source: string | undefined) => {
  switch (source) {
    case 'telegram': return 'Telegram'
    case 'whatsapp': return 'WhatsApp'
    case 'web': return 'Web App'
    default: return 'Web App'
  }
}

// Simple debounce implementation
const debounce = (fn: Function, delay: number) => {
  let timeoutId: number
  return (...args: any[]) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn.apply(null, args), delay)
  }
}

// Watch for filter changes
watch(() => filters.value.search, debounce(() => {
  currentPage.value = 1
}, 500))

watch(() => [filters.value.status, filters.value.dateRange, filters.value.sortBy], () => {
  currentPage.value = 1
})

// Lifecycle
onMounted(() => {
  loadPayslips()
  loadAnalytics()
})
</script> 