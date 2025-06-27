<template>
  <div class="space-y-6">
    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
        <CardContent class="p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-blue-100 text-sm">Total Processed</p>
              <p class="text-2xl font-bold">{{ analytics.total_processed || 0 }}</p>
            </div>
            <FileText class="h-8 w-8 text-blue-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-green-500 to-green-600 text-white">
        <CardContent class="p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-green-100 text-sm">Success Rate</p>
              <p class="text-2xl font-bold">{{ analytics.success_rate || 0 }}%</p>
            </div>
            <CheckCircle class="h-8 w-8 text-green-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-purple-500 to-purple-600 text-white">
        <CardContent class="p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-purple-100 text-sm">Avg Processing</p>
              <p class="text-2xl font-bold">{{ formatProcessingTime(analytics.average_processing_time || 0) }}</p>
            </div>
            <Clock class="h-8 w-8 text-purple-200" />
          </div>
        </CardContent>
      </Card>
      
      <Card class="bg-gradient-to-r from-orange-500 to-orange-600 text-white">
        <CardContent class="p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-orange-100 text-sm">High Confidence</p>
              <p class="text-2xl font-bold">{{ analytics.quality_metrics?.high_confidence || 0 }}</p>
            </div>
            <TrendingUp class="h-8 w-8 text-orange-200" />
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Filters -->
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Filter class="h-5 w-5" />
          Filters & Search
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="space-y-2">
            <Label>Search</Label>
            <div class="relative">
              <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="filters.search"
                placeholder="Search files..."
                class="pl-9"
              />
            </div>
          </div>
          
          <div class="space-y-2">
            <Label>Status</Label>
            <Select v-model:value="filters.status">
              <SelectTrigger>
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All statuses</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
                <SelectItem value="processing">Processing</SelectItem>
                <SelectItem value="queued">Queued</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          <div class="space-y-2">
            <Label>Date Range</Label>
            <Select v-model:value="filters.dateRange">
              <SelectTrigger>
                <SelectValue placeholder="Select range" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="today">Today</SelectItem>
                <SelectItem value="week">This Week</SelectItem>
                <SelectItem value="month">This Month</SelectItem>
                <SelectItem value="all">All Time</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          <div class="space-y-2">
            <Label>Sort By</Label>
            <Select v-model:value="filters.sortBy">
              <SelectTrigger>
                <SelectValue placeholder="Sort by" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="created_at">Date Created</SelectItem>
                <SelectItem value="processing_completed_at">Date Completed</SelectItem>
                <SelectItem value="confidence_score">Confidence</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        
        <div class="flex items-center justify-between pt-4">
          <Button variant="outline" size="sm" @click="clearFilters">
            <X class="h-4 w-4 mr-2" />
            Clear Filters
          </Button>
          <Button variant="outline" size="sm" @click="refreshData">
            <RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': isRefreshing }" />
            Refresh
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Payslips Table -->
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <List class="h-5 w-5" />
          Processing History ({{ filteredPayslips.length }})
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="isLoading" class="flex items-center justify-center p-8">
          <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
          <span class="ml-2 text-muted-foreground">Loading history...</span>
        </div>
        
        <div v-else-if="filteredPayslips.length === 0" class="text-center p-8">
          <FileText class="h-12 w-12 text-muted-foreground mx-auto mb-4" />
          <h3 class="text-lg font-medium">No payslips found</h3>
          <p class="text-muted-foreground mt-1">Try adjusting your filters</p>
        </div>
        
        <div v-else class="space-y-2">
          <div 
            v-for="payslip in paginatedPayslips" 
            :key="payslip.id"
            class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
          >
            <div class="flex items-center space-x-4 flex-1">
              <div class="w-12 h-12 bg-muted rounded-lg flex items-center justify-center">
                <FileText class="h-6 w-6 text-muted-foreground" />
              </div>
              
              <div class="flex-1 min-w-0">
                <p class="font-medium truncate">{{ payslip.name }}</p>
                <div class="flex items-center space-x-2 text-sm text-muted-foreground">
                  <span>{{ formatFileSize(payslip.size) }}</span>
                  <span>•</span>
                  <span>{{ formatDate(payslip.created_at) }}</span>
                  <span v-if="payslip.extracted_data?.nama">•</span>
                  <span v-if="payslip.extracted_data?.nama">{{ payslip.extracted_data.nama }}</span>
                </div>
              </div>
            </div>
            
            <div class="flex items-center space-x-4">
              <Badge :variant="getStatusVariant(payslip.status)">
                {{ payslip.status }}
              </Badge>
              
              <div class="text-center">
                <div class="text-sm font-medium">
                  {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
                </div>
                <div class="text-xs text-muted-foreground">confidence</div>
              </div>
              
              <div class="text-center">
                <div class="text-sm font-medium">
                  {{ formatProcessingTime(payslip.quality_metrics?.processing_time || 0) }}
                </div>
                <div class="text-xs text-muted-foreground">time</div>
              </div>
              
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon">
                    <MoreHorizontal class="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem @click="viewPayslip(payslip)">
                    <Eye class="h-4 w-4 mr-2" />
                    View Details
                  </DropdownMenuItem>
                  <DropdownMenuItem 
                    v-if="payslip.status === 'failed'"
                    @click="reprocessPayslip(payslip)"
                  >
                    <RefreshCw class="h-4 w-4 mr-2" />
                    Reprocess
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem 
                    @click="deletePayslip(payslip)"
                    class="text-red-600"
                  >
                    <Trash2 class="h-4 w-4 mr-2" />
                    Delete
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
          
          <!-- Pagination -->
          <div v-if="totalPages > 1" class="flex items-center justify-center pt-4 space-x-2">
            <Button 
              variant="outline" 
              size="sm" 
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
            >
              <ChevronLeft class="h-4 w-4" />
            </Button>
            
            <span class="text-sm text-muted-foreground">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            
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
      </CardContent>
    </Card>
  </div>
  
  <!-- Payslip Details Modal -->
  <Dialog v-model:open="isDetailsModalOpen">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-auto">
      <DialogHeader>
        <DialogTitle>Payslip Details</DialogTitle>
        <DialogDescription v-if="selectedPayslip">
          {{ selectedPayslip.name }} • {{ formatDate(selectedPayslip.created_at) }}
        </DialogDescription>
      </DialogHeader>
      
      <div v-if="selectedPayslip" class="space-y-6">
        <!-- Quality Metrics -->
        <div class="grid grid-cols-3 gap-4">
          <Card>
            <CardContent class="p-4 text-center">
              <div class="text-2xl font-bold text-blue-600">
                {{ Math.round(selectedPayslip.quality_metrics?.confidence_score || 0) }}%
              </div>
              <div class="text-sm text-muted-foreground">Confidence</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardContent class="p-4 text-center">
              <div class="text-2xl font-bold text-green-600">
                {{ Math.round(selectedPayslip.quality_metrics?.data_completeness || 0) }}%
              </div>
              <div class="text-sm text-muted-foreground">Completeness</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardContent class="p-4 text-center">
              <div class="text-2xl font-bold text-purple-600">
                {{ formatProcessingTime(selectedPayslip.quality_metrics?.processing_time || 0) }}
              </div>
              <div class="text-sm text-muted-foreground">Processing Time</div>
            </CardContent>
          </Card>
        </div>
        
        <!-- Extracted Data -->
        <Card>
          <CardHeader>
            <CardTitle>Extracted Data</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-2 gap-6">
              <div class="space-y-4">
                <div>
                  <Label class="text-sm font-medium">Employee Name</Label>
                  <p class="mt-1">{{ selectedPayslip.extracted_data?.nama || 'Not found' }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Employee Number</Label>
                  <p class="mt-1">{{ selectedPayslip.extracted_data?.no_gaji || 'Not found' }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Month/Year</Label>
                  <p class="mt-1">{{ selectedPayslip.extracted_data?.bulan || 'Not found' }}</p>
                </div>
              </div>
              <div class="space-y-4">
                <div>
                  <Label class="text-sm font-medium">Basic Salary</Label>
                  <p class="mt-1">RM {{ formatCurrency(selectedPayslip.extracted_data?.gaji_pokok) }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Net Salary</Label>
                  <p class="mt-1">RM {{ formatCurrency(selectedPayslip.extracted_data?.gaji_bersih) }}</p>
                </div>
                <div>
                  <Label class="text-sm font-medium">Percentage</Label>
                  <p class="mt-1">{{ selectedPayslip.extracted_data?.peratus_gaji_bersih || 'Not found' }}%</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
        
        <!-- Koperasi Results -->
        <Card v-if="selectedPayslip.koperasi_summary">
          <CardHeader>
            <CardTitle>Koperasi Eligibility</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-center">
              <div class="text-3xl font-bold text-green-600 mb-2">
                {{ selectedPayslip.koperasi_summary.eligible_count }}
              </div>
              <div class="text-muted-foreground">
                out of {{ selectedPayslip.koperasi_summary.total_checked }} koperasi eligible
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { debounce } from 'lodash-es'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { 
  FileText, CheckCircle, Clock, TrendingUp, Filter, Search, X, RefreshCw,
  List, LoaderCircle, Eye, Trash2, MoreHorizontal, ChevronLeft, ChevronRight
} from 'lucide-vue-next'

interface Payslip {
  id: number
  name: string
  status: string
  size: number
  created_at: string
  extracted_data?: any
  quality_metrics?: any
  koperasi_summary?: any
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
const selectedPayslip = ref<Payslip | null>(null)
const isDetailsModalOpen = ref(false)

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
      p.extracted_data?.nama?.toLowerCase().includes(search)
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
const loadPayslips = async () => {
  isLoading.value = true
  try {
    const response = await fetch('/api/payslips')
    const data = await response.json()
    payslips.value = data.payslips || data || []
  } catch (error) {
    console.error('Failed to load payslips:', error)
  } finally {
    isLoading.value = false
  }
}

const loadAnalytics = async () => {
  try {
    const response = await fetch('/api/payslips/analytics')
    analytics.value = await response.json()
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

const viewPayslip = async (payslip: Payslip) => {
  try {
    const response = await fetch(`/api/payslips/${payslip.id}`)
    selectedPayslip.value = await response.json()
    isDetailsModalOpen.value = true
  } catch (error) {
    console.error('Failed to load payslip details:', error)
  }
}

const reprocessPayslip = async (payslip: Payslip) => {
  try {
    await fetch(`/api/payslips/${payslip.id}/reprocess`, { method: 'POST' })
    loadPayslips()
  } catch (error) {
    console.error('Failed to reprocess payslip:', error)
  }
}

const deletePayslip = async (payslip: Payslip) => {
  if (confirm('Are you sure you want to delete this payslip?')) {
    try {
      await fetch(`/api/payslips/${payslip.id}`, { method: 'DELETE' })
      loadPayslips()
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
  if (!amount) return '0.00'
  return amount.toLocaleString('en-MY', { minimumFractionDigits: 2 })
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