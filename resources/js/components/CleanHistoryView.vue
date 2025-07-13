<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-16">
      <div class="flex flex-col items-center space-y-3">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-muted border-t-primary"></div>
        <p class="text-sm text-muted-foreground">Loading AI-processed payslips...</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="payslips.length === 0" class="flex flex-col items-center justify-center py-16">
      <div class="h-20 w-20 rounded-full bg-gradient-to-r from-blue-50 to-purple-50 flex items-center justify-center mb-6">
        <FileText class="h-10 w-10 text-blue-600" />
      </div>
      <h3 class="text-xl font-semibold mb-2">No AI-processed payslips yet</h3>
      <p class="text-muted-foreground text-center max-w-md">
        Your Google Vision API processing history will appear here once you upload and process your first payslip.
      </p>
    </div>

    <!-- AI Processing Statistics -->
    <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-950/50 dark:to-blue-900/50 p-6">
        <div class="flex items-center space-x-3 mb-3">
          <div class="h-10 w-10 bg-blue-500/10 rounded-full flex items-center justify-center">
            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">AI Processed</p>
            <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ stats.totalProcessed }}</p>
          </div>
        </div>
        <p class="text-xs text-blue-700 dark:text-blue-300">Total payslips processed with Google Vision API</p>
      </div>
      
      <div class="rounded-lg border bg-gradient-to-r from-green-50 to-green-100 dark:from-green-950/50 dark:to-green-900/50 p-6">
        <div class="flex items-center space-x-3 mb-3">
          <div class="h-10 w-10 bg-green-500/10 rounded-full flex items-center justify-center">
            <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-green-900 dark:text-green-100">Success Rate</p>
            <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ stats.successRate }}%</p>
          </div>
        </div>
        <p class="text-xs text-green-700 dark:text-green-300">AI processing accuracy rate</p>
      </div>
      
      <div class="rounded-lg border bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-950/50 dark:to-purple-900/50 p-6">
        <div class="flex items-center space-x-3 mb-3">
          <div class="h-10 w-10 bg-purple-500/10 rounded-full flex items-center justify-center">
            <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-purple-900 dark:text-purple-100">Avg Speed</p>
            <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ stats.avgProcessingTime }}s</p>
          </div>
        </div>
        <p class="text-xs text-purple-700 dark:text-purple-300">Average Google Vision API processing time</p>
      </div>
      
      <div class="rounded-lg border bg-gradient-to-r from-orange-50 to-orange-100 dark:from-orange-950/50 dark:to-orange-900/50 p-6">
        <div class="flex items-center space-x-3 mb-3">
          <div class="h-10 w-10 bg-orange-500/10 rounded-full flex items-center justify-center">
            <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-orange-900 dark:text-orange-100">Avg Confidence</p>
            <p class="text-2xl font-bold text-orange-900 dark:text-orange-100">{{ stats.avgConfidence }}%</p>
          </div>
        </div>
        <p class="text-xs text-orange-700 dark:text-orange-300">Average AI extraction confidence</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4 p-6 border rounded-lg bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-950/50 dark:to-gray-900/50">
      <div class="flex-1">
        <div class="relative">
          <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            placeholder="Search AI-processed payslips..."
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
            <DropdownMenuItem @click="setFilter('status', '')">All</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'completed')">✅ Completed</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'failed')">❌ Failed</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'processing')">🔄 Processing</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('status', 'queued')">⏳ Queued</DropdownMenuItem>
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
            <DropdownMenuItem @click="setFilter('dateRange', 'week')">This Week</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'month')">This Month</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'quarter')">This Quarter</DropdownMenuItem>
            <DropdownMenuItem @click="setFilter('dateRange', 'year')">This Year</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        
        <Button variant="outline" size="sm" @click="clearFilters" v-if="hasActiveFilters">
          <X class="h-4 w-4" />
        </Button>
      </div>
    </div>

    <!-- AI-Processed Payslips List -->
    <div class="space-y-4">
      <div
        v-for="payslip in paginatedPayslips"
        :key="payslip.id"
        class="border rounded-lg bg-card shadow-sm hover:shadow-md transition-shadow"
      >
        <div 
          class="flex items-center justify-between p-6 cursor-pointer hover:bg-muted/50 transition-colors"
          @click="toggleExpanded(payslip.id)"
        >
          <div class="flex items-center space-x-4 flex-1">
            <!-- File Icon with AI indicator -->
            <div class="relative h-12 w-12 rounded-lg bg-gradient-to-r from-blue-50 to-purple-50 flex items-center justify-center">
              <FileText class="h-6 w-6 text-blue-600" />
              <div class="absolute -top-1 -right-1 h-5 w-5 bg-blue-500 rounded-full flex items-center justify-center">
                <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                </svg>
              </div>
            </div>
            
            <div class="flex-1 min-w-0">
              <div class="flex items-center space-x-3 mb-2">
                <p class="text-sm font-medium truncate">{{ payslip.name }}</p>
                
                <!-- AI Processing Status -->
                <div class="flex items-center space-x-2">
                  <div :class="getStatusIndicatorClasses(payslip.status)" class="w-2 h-2 rounded-full"></div>
                  <span :class="getStatusTextClasses(payslip.status)" class="text-xs font-medium">
                    {{ getStatusLabel(payslip.status) }}
                  </span>
                </div>
                
                <!-- Processing Method Badge -->
                <Badge variant="secondary" class="text-xs">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                  </svg>
                  Google Vision API
                </Badge>
                
                <!-- Source indicator -->
                <div class="flex items-center space-x-1">
                  <component :is="getSourceIcon(payslip.source)" class="w-4 h-4 text-muted-foreground" />
                  <span class="text-xs text-muted-foreground font-medium">
                    {{ getSourceLabel(payslip.source) }}
                  </span>
                </div>
              </div>
              
              <div class="flex items-center space-x-4 text-sm text-muted-foreground">
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                  {{ payslip.data?.nama || 'Unknown Employee' }}
                </span>
                <span>•</span>
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  {{ payslip.data?.bulan || formatDate(payslip.created_at) }}
                </span>
                <span>•</span>
                <span class="flex items-center gap-1 font-medium">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                  </svg>
                  {{ formatCurrency(payslip.data?.gaji_bersih) }}
                </span>
              </div>
            </div>
          </div>
          
          <div class="flex items-center space-x-4">
            <!-- AI Confidence Score -->
            <div class="text-right">
              <div class="flex items-center gap-2">
                <div class="w-12 h-2 bg-muted rounded-full overflow-hidden">
                  <div 
                    class="h-full bg-gradient-to-r from-green-500 to-blue-500 transition-all duration-300"
                    :style="{ width: `${Math.round(payslip.quality_metrics?.confidence_score || 0)}%` }"
                  ></div>
                </div>
                <span class="text-sm font-medium">{{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%</span>
              </div>
              <p class="text-xs text-muted-foreground">AI Confidence</p>
            </div>
            
            <!-- Data Completeness -->
            <div class="text-right">
              <div class="flex items-center gap-2">
                <div class="w-12 h-2 bg-muted rounded-full overflow-hidden">
                  <div 
                    class="h-full bg-gradient-to-r from-blue-500 to-purple-500 transition-all duration-300"
                    :style="{ width: `${Math.round(payslip.quality_metrics?.data_completeness || 0)}%` }"
                  ></div>
                </div>
                <span class="text-sm font-medium">{{ Math.round(payslip.quality_metrics?.data_completeness || 0) }}%</span>
              </div>
              <p class="text-xs text-muted-foreground">Data Complete</p>
            </div>
            
            <ChevronDown 
              :class="['h-5 w-5 transition-transform text-muted-foreground', expandedItems.has(payslip.id) && 'rotate-180']"
            />
          </div>
        </div>
        
        <!-- Expanded AI Processing Details -->
        <div v-if="expandedItems.has(payslip.id)" class="border-t bg-gradient-to-r from-blue-50/50 to-purple-50/50 dark:from-blue-950/20 dark:to-purple-950/20 p-6">
          <!-- AI Processing Summary -->
          <div class="bg-white dark:bg-gray-950 rounded-lg p-4 mb-6 border border-blue-200 dark:border-blue-800">
            <div class="flex items-center gap-3 mb-4">
              <div class="p-2 bg-blue-500/10 rounded-full">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-blue-900 dark:text-blue-100">Google Vision API Processing Results</h4>
                <p class="text-sm text-blue-700 dark:text-blue-300">Advanced AI text extraction and analysis</p>
              </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="text-center">
                <div class="text-lg font-bold text-blue-600">{{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%</div>
                <div class="text-xs text-muted-foreground">Confidence Score</div>
              </div>
              <div class="text-center">
                <div class="text-lg font-bold text-green-600">{{ Math.round(payslip.quality_metrics?.data_completeness || 0) }}%</div>
                <div class="text-xs text-muted-foreground">Data Completeness</div>
              </div>
              <div class="text-center">
                <div class="text-lg font-bold text-purple-600">{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</div>
                <div class="text-xs text-muted-foreground">Processing Time</div>
              </div>
              <div class="text-center">
                <div class="text-lg font-bold text-orange-600">{{ payslip.koperasi_summary?.eligible_count || 0 }}/{{ payslip.koperasi_summary?.total_checked || 0 }}</div>
                <div class="text-xs text-muted-foreground">Eligible/Total</div>
              </div>
            </div>
          </div>
          
          <div class="grid gap-6 md:grid-cols-2">
            <!-- AI Extracted Data -->
            <div class="space-y-4">
              <h4 class="text-sm font-medium flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                AI Extracted Information
              </h4>
              <div class="bg-white dark:bg-gray-950 rounded-lg p-4 border">
                <div class="space-y-3">
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Employee Name</span>
                    <span class="font-medium">{{ payslip.data?.nama || 'Not extracted' }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Employee ID</span>
                    <span class="font-medium">{{ payslip.data?.no_gaji || 'Not extracted' }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Pay Period</span>
                    <span class="font-medium">{{ payslip.data?.bulan || 'Not extracted' }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Salary Percentage</span>
                    <span class="font-medium">{{ payslip.data?.peratus_gaji_bersih ? payslip.data.peratus_gaji_bersih + '%' : 'Not extracted' }}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- AI Salary Analysis -->
            <div class="space-y-4">
              <h4 class="text-sm font-medium flex items-center gap-2">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                </svg>
                AI Salary Analysis
              </h4>
              <div class="bg-white dark:bg-gray-950 rounded-lg p-4 border">
                <div class="space-y-3">
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Basic Salary</span>
                    <span class="font-medium">{{ formatCurrency(payslip.data?.gaji_pokok) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Total Earnings</span>
                    <span class="font-medium">{{ formatCurrency(payslip.data?.jumlah_pendapatan) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Total Deductions</span>
                    <span class="font-medium">{{ formatCurrency(payslip.data?.jumlah_potongan) }}</span>
                  </div>
                  <div class="flex justify-between text-sm font-semibold pt-2 border-t">
                    <span>Net Salary</span>
                    <span class="text-green-600">{{ formatCurrency(payslip.data?.gaji_bersih) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- AI Koperasi Eligibility Results -->
          <div v-if="payslip.data?.koperasi_results && Object.keys(payslip.data.koperasi_results).length > 0" class="mt-6 pt-6 border-t">
            <h4 class="text-sm font-medium mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-2m-2 0H7m14 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v12"/>
              </svg>
              AI Eligibility Analysis
            </h4>
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="(isEligible, koperasiName) in payslip.data.koperasi_results"
                :key="koperasiName"
                class="flex items-center justify-between p-3 rounded-lg border bg-white dark:bg-gray-950"
              >
                <span class="font-medium text-sm">{{ koperasiName }}</span>
                <Badge 
                  :variant="isEligible ? 'default' : 'secondary'" 
                  :class="isEligible ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200'"
                  class="text-xs"
                >
                  {{ isEligible ? '✓ Eligible' : '✗ Not Eligible' }}
                </Badge>
              </div>
            </div>
          </div>
          
          <!-- Actions -->
          <div class="flex justify-end space-x-3 mt-6 pt-6 border-t">
            <Button variant="outline" size="sm" @click="handleView(payslip)" class="flex items-center gap-2">
              <Eye class="h-4 w-4" />
              View Details
            </Button>
            <Button variant="outline" size="sm" @click="handleDownload(payslip)" class="flex items-center gap-2">
              <Download class="h-4 w-4" />
              Download Report
            </Button>
            <Button variant="outline" size="sm" @click="handleDelete(payslip)" class="text-destructive flex items-center gap-2">
              <Trash2 class="h-4 w-4" />
              Delete
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex items-center justify-between pt-6">
      <p class="text-sm text-muted-foreground">
        Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + itemsPerPage, filteredPayslips.length) }} of {{ filteredPayslips.length }} AI-processed payslips
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
            :variant="page === currentPage ? 'default' : 'outline'"
            size="sm"
            @click="goToPage(page)"
            class="w-8 h-8"
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
  CheckCircle,
  XCircle,
  Loader2,
  Clock,
  MessageSquare,
  Globe
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
const filteredPayslips = computed(() => {
  let filtered = payslips.value

  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(payslip => 
      payslip.name.toLowerCase().includes(query) ||
      payslip.data?.nama?.toLowerCase().includes(query) ||
      payslip.data?.no_gaji?.toLowerCase().includes(query) ||
      payslip.data?.bulan?.toLowerCase().includes(query)
    )
  }

  // Apply status filter
  if (activeFilters.value.status) {
    filtered = filtered.filter(payslip => payslip.status === activeFilters.value.status)
  }

  // Apply date range filter
  if (activeFilters.value.dateRange) {
    const now = new Date()
    const filterDate = new Date()
    
    switch (activeFilters.value.dateRange) {
      case 'week':
        filterDate.setDate(now.getDate() - 7)
        break
      case 'month':
        filterDate.setMonth(now.getMonth() - 1)
        break
      case 'quarter':
        filterDate.setMonth(now.getMonth() - 3)
        break
      case 'year':
        filterDate.setFullYear(now.getFullYear() - 1)
        break
    }
    
    filtered = filtered.filter(payslip => new Date(payslip.created_at) >= filterDate)
  }

  return filtered
})

const paginatedPayslips = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredPayslips.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredPayslips.value.length / itemsPerPage.value))

const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value)

const visiblePageNumbers = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)
  
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  
  return pages
})

const hasActiveFilters = computed(() => {
  return Object.values(activeFilters.value).some(value => value !== '' && value !== 'month')
})

// Methods
const loadPayslips = async () => {
  isLoading.value = true
  try {
    const response = await fetch('/api/payslips')
    if (response.ok) {
      const data = await response.json()
      payslips.value = data.payslips || []
      calculateStats()
    }
  } catch (error) {
    console.error('Failed to load payslips:', error)
  } finally {
    isLoading.value = false
  }
}

const calculateStats = () => {
  const total = payslips.value.length
  const successful = payslips.value.filter(p => p.status === 'completed').length
  const totalProcessingTime = payslips.value
    .filter(p => p.quality_metrics?.processing_time)
    .reduce((sum, p) => sum + (p.quality_metrics?.processing_time || 0), 0)
  const totalConfidence = payslips.value
    .filter(p => p.quality_metrics?.confidence_score)
    .reduce((sum, p) => sum + (p.quality_metrics?.confidence_score || 0), 0)
  
  stats.value = {
    totalProcessed: total,
    successRate: total > 0 ? Math.round((successful / total) * 100) : 0,
    avgProcessingTime: totalProcessingTime > 0 ? Math.round(totalProcessingTime / successful) : 0,
    avgConfidence: totalConfidence > 0 ? Math.round(totalConfidence / payslips.value.length) : 0
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
  currentPage.value = 1
}

const clearFilters = () => {
  activeFilters.value = {
    status: '',
    dateRange: 'month'
  }
  searchQuery.value = ''
  currentPage.value = 1
}

const getDateRangeLabel = () => {
  const labels = {
    'week': 'This Week',
    'month': 'This Month',
    'quarter': 'This Quarter',
    'year': 'This Year'
  }
  return labels[activeFilters.value.dateRange as keyof typeof labels] || 'This Month'
}

const goToPage = (page: number) => {
  currentPage.value = page
}

const getStatusIndicatorClasses = (status: string) => {
  const classes = {
    'completed': 'bg-green-500',
    'failed': 'bg-red-500',
    'processing': 'bg-blue-500',
    'queued': 'bg-orange-500'
  }
  return classes[status as keyof typeof classes] || 'bg-gray-500'
}

const getStatusTextClasses = (status: string) => {
  const classes = {
    'completed': 'text-green-700',
    'failed': 'text-red-700',
    'processing': 'text-blue-700',
    'queued': 'text-orange-700'
  }
  return classes[status as keyof typeof classes] || 'text-gray-700'
}

const getStatusLabel = (status: string) => {
  const labels = {
    'completed': 'AI Processed',
    'failed': 'Failed',
    'processing': 'Processing',
    'queued': 'Queued'
  }
  return labels[status as keyof typeof labels] || 'Unknown'
}

const getSourceIcon = (source?: string) => {
  const icons = {
    'telegram': MessageSquare,
    'whatsapp': MessageSquare,
    'web': Globe
  }
  return icons[source as keyof typeof icons] || Globe
}

const getSourceLabel = (source?: string) => {
  const labels = {
    'telegram': 'Telegram',
    'whatsapp': 'WhatsApp',
    'web': 'Web App'
  }
  return labels[source as keyof typeof labels] || 'Web App'
}

const formatCurrency = (amount?: number) => {
  if (!amount) return 'N/A'
  return new Intl.NumberFormat('ms-MY', {
    style: 'currency',
    currency: 'MYR'
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatProcessingTime = (seconds?: number) => {
  if (!seconds) return 'N/A'
  return `${seconds.toFixed(1)}s`
}

const handleView = (payslip: Payslip) => {
  // Implementation for viewing payslip details
  console.log('View payslip:', payslip)
}

const handleDownload = (payslip: Payslip) => {
  // Implementation for downloading payslip
  console.log('Download payslip:', payslip)
}

const handleDelete = (payslip: Payslip) => {
  // Implementation for deleting payslip
  console.log('Delete payslip:', payslip)
}

// Lifecycle
onMounted(() => {
  loadPayslips()
})
</script> 