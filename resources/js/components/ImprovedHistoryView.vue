<template>
  <div class="space-y-6">
    <!-- Statistics Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-blue-600/10"></div>
        <CardContent class="p-4 relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
              <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <span class="text-xs text-muted-foreground">Total</span>
          </div>
          <div class="space-y-1">
            <h3 class="text-2xl font-bold">{{ stats.totalProcessed }}</h3>
            <p class="text-xs text-muted-foreground">Payslips Processed</p>
          </div>
          <div class="mt-3 flex items-center text-xs">
            <TrendingUp class="h-3 w-3 text-green-500 mr-1" />
            <span class="text-green-600 dark:text-green-400 font-medium">+{{ stats.monthlyGrowth }}%</span>
            <span class="text-muted-foreground ml-1">this month</span>
          </div>
        </CardContent>
      </Card>

      <Card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-green-500/10 to-green-600/10"></div>
        <CardContent class="p-4 relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
              <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <span class="text-xs text-muted-foreground">Success</span>
          </div>
          <div class="space-y-1">
            <h3 class="text-2xl font-bold">{{ stats.successRate }}%</h3>
            <p class="text-xs text-muted-foreground">Success Rate</p>
          </div>
          <div class="mt-3">
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
              <div 
                class="bg-green-500 h-1.5 rounded-full transition-all duration-500"
                :style="`width: ${stats.successRate}%`"
              ></div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-purple-500/10 to-purple-600/10"></div>
        <CardContent class="p-4 relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
              <Clock class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            </div>
            <span class="text-xs text-muted-foreground">Speed</span>
          </div>
          <div class="space-y-1">
            <h3 class="text-2xl font-bold">{{ stats.avgProcessingTime }}s</h3>
            <p class="text-xs text-muted-foreground">Avg Processing Time</p>
          </div>
          <div class="mt-3 flex items-center text-xs">
            <Zap class="h-3 w-3 text-purple-500 mr-1" />
            <span class="text-muted-foreground">{{ stats.processingSpeed }}% faster than average</span>
          </div>
        </CardContent>
      </Card>

      <Card class="relative overflow-hidden group hover:shadow-lg transition-all duration-300">
        <div class="absolute inset-0 bg-gradient-to-br from-orange-500/10 to-orange-600/10"></div>
        <CardContent class="p-4 relative">
          <div class="flex items-center justify-between mb-2">
            <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
              <Target class="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
            <span class="text-xs text-muted-foreground">Accuracy</span>
          </div>
          <div class="space-y-1">
            <h3 class="text-2xl font-bold">{{ stats.avgConfidence }}%</h3>
            <p class="text-xs text-muted-foreground">Avg Confidence Score</p>
          </div>
          <div class="mt-3 grid grid-cols-3 gap-1">
            <div class="text-center">
              <div class="text-xs font-semibold text-green-600">{{ stats.highConfidence }}</div>
              <div class="text-xs text-muted-foreground">High</div>
            </div>
            <div class="text-center">
              <div class="text-xs font-semibold text-yellow-600">{{ stats.mediumConfidence }}</div>
              <div class="text-xs text-muted-foreground">Med</div>
            </div>
            <div class="text-center">
              <div class="text-xs font-semibold text-red-600">{{ stats.lowConfidence }}</div>
              <div class="text-xs text-muted-foreground">Low</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Search and Filters Bar -->
    <Card class="border-0 shadow-sm">
      <CardContent class="p-4">
        <div class="flex flex-col lg:flex-row gap-4">
          <!-- Search -->
          <div class="flex-1">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="searchQuery"
                placeholder="Search by name, employee ID, or month..."
                class="pl-10 h-10"
                @input="debouncedSearch"
              />
              <kbd v-if="!searchQuery" class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100">
                <span class="text-xs">⌘</span>K
              </kbd>
            </div>
          </div>

          <!-- Quick Filters -->
          <div class="flex gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="default" class="h-10">
                  <Filter class="h-4 w-4 mr-2" />
                  Status
                  <Badge v-if="activeFilters.status" variant="secondary" class="ml-2 h-4 px-1 text-xs">
                    1
                  </Badge>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuCheckboxItem 
                  v-for="status in statusOptions" 
                  :key="status.value"
                  :checked="activeFilters.status === status.value"
                  @click="setFilter('status', status.value)"
                >
                  <div class="flex items-center gap-2">
                    <div :class="['h-2 w-2 rounded-full', status.color]"></div>
                    {{ status.label }}
                  </div>
                </DropdownMenuCheckboxItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="default" class="h-10">
                  <Calendar class="h-4 w-4 mr-2" />
                  {{ dateRangeLabel }}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuLabel>Date Range</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup v-model="activeFilters.dateRange">
                  <DropdownMenuRadioItem value="today">Today</DropdownMenuRadioItem>
                  <DropdownMenuRadioItem value="week">This Week</DropdownMenuRadioItem>
                  <DropdownMenuRadioItem value="month">This Month</DropdownMenuRadioItem>
                  <DropdownMenuRadioItem value="quarter">This Quarter</DropdownMenuRadioItem>
                  <DropdownMenuRadioItem value="year">This Year</DropdownMenuRadioItem>
                  <DropdownMenuRadioItem value="all">All Time</DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
              </DropdownMenuContent>
            </DropdownMenu>

            <Button
              v-if="hasActiveFilters"
              variant="ghost"
              size="default"
              @click="clearAllFilters"
              class="h-10 text-muted-foreground"
            >
              <X class="h-4 w-4 mr-2" />
              Clear
            </Button>
          </div>

          <!-- View Toggle -->
          <div class="flex items-center gap-2 border rounded-lg p-1">
            <Button
              variant="ghost"
              size="sm"
              :class="[viewMode === 'list' ? 'bg-background shadow-sm' : '']"
              @click="viewMode = 'list'"
              class="h-8 px-3"
            >
              <List class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              :class="[viewMode === 'grid' ? 'bg-background shadow-sm' : '']"
              @click="viewMode = 'grid'"
              class="h-8 px-3"
            >
              <Grid class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <!-- Active Filters Display -->
        <div v-if="hasActiveFilters" class="flex items-center gap-2 mt-3">
          <span class="text-sm text-muted-foreground">Active filters:</span>
          <div class="flex gap-2">
                      <template v-for="(value, key) in activeFilters" :key="key">
            <Badge
              v-if="value && value !== 'month'"
              variant="secondary"
              class="pl-2 pr-1 py-1"
            >
              {{ getFilterLabel(key as string, value) }}
              <Button
                variant="ghost"
                size="sm"
                @click="removeFilter(key as string)"
                class="h-4 w-4 p-0 ml-1 hover:bg-transparent"
              >
                <X class="h-3 w-3" />
              </Button>
            </Badge>
          </template>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Payslips List/Grid -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-12">
      <LoaderCircle class="h-8 w-8 animate-spin text-primary mb-4" />
      <p class="text-muted-foreground">Loading your payslip history...</p>
    </div>

    <div v-else-if="filteredPayslips.length === 0" class="text-center py-12">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-muted rounded-full mb-4">
        <FileX class="h-8 w-8 text-muted-foreground" />
      </div>
      <h3 class="text-lg font-semibold mb-2">No payslips found</h3>
      <p class="text-muted-foreground max-w-sm mx-auto">
        {{ searchQuery || hasActiveFilters ? 'Try adjusting your filters or search terms.' : 'Upload your first payslip to get started.' }}
      </p>
      <Button v-if="hasActiveFilters" variant="outline" @click="clearAllFilters" class="mt-4">
        Clear Filters
      </Button>
    </div>

    <!-- List View -->
    <div v-else-if="viewMode === 'list'" class="space-y-4">
      <div
        v-for="payslip in paginatedPayslips"
        :key="payslip.id"
        class="group relative bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-200 dark:border-gray-700 overflow-hidden"
      >
        <!-- Modern Header with gradient accent -->
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500"></div>
        
        <div class="p-6">
          <!-- Top Row: File info and status -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-4 flex-1">
              <!-- Enhanced file icon -->
              <div class="relative">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 dark:from-gray-800 dark:via-gray-700 dark:to-gray-600 rounded-2xl flex items-center justify-center shadow-sm border border-gray-200 dark:border-gray-600">
                  <FileText class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                </div>
                <!-- Source badge -->
                <div v-if="payslip.source" 
                     class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full shadow-lg flex items-center justify-center"
                     :class="{
                       'bg-blue-500': payslip.source === 'telegram',
                       'bg-green-500': payslip.source === 'whatsapp',
                       'bg-purple-500': payslip.source === 'web'
                     }">
                  <Globe v-if="payslip.source === 'web'" class="h-4 w-4 text-white" />
                  <Eye v-else class="h-4 w-4 text-white" />
                </div>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                    {{ payslip.name }}
                  </h3>
                  <Badge 
                    :class="{
                      'bg-green-100 text-green-800 border-green-200': payslip.status === 'completed',
                      'bg-red-100 text-red-800 border-red-200': payslip.status === 'failed',
                      'bg-blue-100 text-blue-800 border-blue-200': payslip.status === 'processing',
                      'bg-yellow-100 text-yellow-800 border-yellow-200': payslip.status === 'queued'
                    }"
                    class="px-3 py-1 text-sm font-medium rounded-full border"
                  >
                    {{ payslip.status.charAt(0).toUpperCase() + payslip.status.slice(1) }}
                  </Badge>
                </div>
                
                <!-- Employee info in modern pill format -->
                <div class="flex flex-wrap gap-2 text-sm">
                  <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-full px-3 py-1">
                    <div class="w-2 h-2 bg-indigo-500 rounded-full"></div>
                    <span class="text-gray-700 dark:text-gray-300">{{ payslip.extracted_data?.nama || 'Unknown Employee' }}</span>
                  </div>
                  <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-full px-3 py-1">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                    <span class="text-gray-700 dark:text-gray-300">{{ payslip.extracted_data?.no_gaji || 'No ID' }}</span>
                  </div>
                  <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-full px-3 py-1">
                    <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
                    <span class="text-gray-700 dark:text-gray-300">{{ payslip.extracted_data?.bulan || formatDate(payslip.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions dropdown -->
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" class="opacity-0 group-hover:opacity-100 transition-opacity">
                  <X class="h-4 w-4 rotate-45" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-48">
                <DropdownMenuItem @click="handleView(payslip)" class="flex items-center gap-2">
                  <Eye class="h-4 w-4" />
                  View Details
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleDownload(payslip)" class="flex items-center gap-2">
                  <Download class="h-4 w-4" />
                  Download PDF
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="handleDelete(payslip)" class="flex items-center gap-2 text-red-600">
                  <X class="h-4 w-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <!-- Metrics Row -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <!-- Net Salary -->
            <div class="text-center p-4 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl border border-green-200 dark:border-green-800">
              <div class="text-2xl font-bold text-green-700 dark:text-green-300">
                {{ formatCurrency(payslip.extracted_data?.gaji_bersih) }}
              </div>
              <div class="text-xs font-medium text-green-600 dark:text-green-400 mt-1">Net Salary</div>
            </div>

            <!-- Confidence Score -->
            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
              <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">
                {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
              </div>
              <div class="text-xs font-medium text-blue-600 dark:text-blue-400 mt-1">Confidence</div>
            </div>

            <!-- Processing Time -->
            <div class="text-center p-4 bg-gradient-to-br from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20 rounded-xl border border-purple-200 dark:border-purple-800">
              <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">
                {{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}
              </div>
              <div class="text-xs font-medium text-purple-600 dark:text-purple-400 mt-1">Process Time</div>
            </div>

            <!-- Koperasi Eligible -->
            <div class="text-center p-4 bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 rounded-xl border border-orange-200 dark:border-orange-800">
              <div class="text-2xl font-bold text-orange-700 dark:text-orange-300">
                {{ payslip.koperasi_summary?.eligible_count || 0 }}/{{ payslip.koperasi_summary?.total_checked || 0 }}
              </div>
              <div class="text-xs font-medium text-orange-600 dark:text-orange-400 mt-1">Koperasi</div>
            </div>
          </div>

          <!-- Expandable Details Button -->
          <Button
            variant="ghost"
            @click="toggleExpanded(payslip.id)"
            class="w-full justify-between text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
          >
            {{ expandedItems.has(payslip.id) ? 'Hide Details' : 'Show More Details' }}
            <ChevronRight :class="['h-4 w-4 transition-transform', expandedItems.has(payslip.id) && 'rotate-90']" />
          </Button>

          <!-- Expanded Details -->
          <div v-if="expandedItems.has(payslip.id)" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Salary Breakdown -->
              <div class="space-y-3">
                <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Salary Breakdown</h4>
                <div class="space-y-2">
                  <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Basic Salary</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(payslip.extracted_data?.gaji_pokok) }}</span>
                  </div>
                  <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Earnings</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(payslip.extracted_data?.jumlah_pendapatan) }}</span>
                  </div>
                  <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Deductions</span>
                    <span class="font-medium text-red-600 dark:text-red-400">{{ formatCurrency(payslip.extracted_data?.jumlah_potongan) }}</span>
                  </div>
                </div>
              </div>

              <!-- Koperasi Details -->
              <div v-if="payslip.extracted_data?.koperasi_results" class="space-y-3">
                <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Koperasi Eligibility</h4>
                <div class="space-y-2">
                  <div
                    v-for="(isEligible, koperasiName) in payslip.extracted_data.koperasi_results"
                    :key="koperasiName"
                    class="flex items-center justify-between p-2 rounded-lg"
                    :class="isEligible ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'"
                  >
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ koperasiName }}</span>
                    <Badge :variant="isEligible ? 'default' : 'secondary'" class="text-xs">
                      {{ isEligible ? '✓ Eligible' : '✗ Not Eligible' }}
                    </Badge>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Grid View -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div
        v-for="payslip in paginatedPayslips"
        :key="payslip.id"
        class="group bg-white dark:bg-gray-800 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 overflow-hidden"
      >
        <!-- Modern card header with status ribbon -->
        <div class="relative">
          <div class="h-24 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 relative">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute top-4 right-4">
                             <Badge 
                 :class="`text-white border-white/20 ${
                   payslip.status === 'completed' ? 'bg-green-500/90' :
                   payslip.status === 'failed' ? 'bg-red-500/90' :
                   payslip.status === 'processing' ? 'bg-blue-500/90' :
                   payslip.status === 'queued' ? 'bg-yellow-500/90' : 'bg-gray-500/90'
                 }`"
               >
                {{ payslip.status.charAt(0).toUpperCase() + payslip.status.slice(1) }}
              </Badge>
            </div>
          </div>
          
          <!-- File icon floating -->
          <div class="absolute -bottom-8 left-6">
            <div class="w-16 h-16 bg-white dark:bg-gray-800 rounded-2xl shadow-lg flex items-center justify-center border border-gray-200 dark:border-gray-600">
              <FileText class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
            </div>
          </div>
        </div>

        <div class="pt-12 p-6">
          <!-- File and employee info -->
          <div class="mb-4">
            <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-2 truncate">
              {{ payslip.name }}
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
              {{ payslip.extracted_data?.nama || 'Unknown Employee' }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-500">
              {{ payslip.extracted_data?.bulan || formatDate(payslip.created_at) }}
            </p>
          </div>

          <!-- Key metrics -->
          <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-xl">
              <div class="text-lg font-bold text-green-700 dark:text-green-300">
                {{ formatShortCurrency(payslip.extracted_data?.gaji_bersih) }}
              </div>
              <div class="text-xs text-green-600 dark:text-green-400">Net Salary</div>
            </div>
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
              <div class="text-lg font-bold text-blue-700 dark:text-blue-300">
                {{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%
              </div>
              <div class="text-xs text-blue-600 dark:text-blue-400">Confidence</div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-2">
            <Button variant="outline" size="sm" @click="handleView(payslip)" class="flex-1">
              <Eye class="h-4 w-4 mr-2" />
              View
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon">
                  <X class="h-4 w-4 rotate-45" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @click="handleDownload(payslip)">
                  <Download class="h-4 w-4 mr-2" />
                  Download
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="handleDelete(payslip)" class="text-red-600">
                  <X class="h-4 w-4 mr-2" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex items-center justify-between">
      <p class="text-sm text-muted-foreground">
        Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ filteredPayslips.length }} results
      </p>
      
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
        >
          <ChevronLeft class="h-4 w-4" />
          Previous
        </Button>

        <div class="flex items-center gap-1">
          <Button
            v-for="page in visiblePageNumbers"
            :key="page"
            variant="outline"
            size="sm"
            @click="goToPage(page)"
            :class="[currentPage === page ? 'bg-primary text-primary-foreground hover:bg-primary/90' : '']"
            class="min-w-10"
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
          Next
          <ChevronRight class="h-4 w-4" />
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuCheckboxItem,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu'
import { 
  FileText, CheckCircle, Clock, Target, TrendingUp, Search, Filter,
  Calendar, X, List, Grid, LoaderCircle, FileX, ChevronLeft, ChevronRight,
  Zap, Eye, Download, Globe
} from 'lucide-vue-next'
// import PayslipListItem from './PayslipListItem.vue'
// import PayslipGridCard from './PayslipGridCard.vue'

// Type definitions
interface Payslip {
  id: number
  name: string
  status: 'completed' | 'failed' | 'processing' | 'queued'
  size: number
  source?: string
  created_at: string
  processing_completed_at?: string
  processing_error?: string
  extracted_data?: {
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
  highConfidence: number
  mediumConfidence: number
  lowConfidence: number
  monthlyGrowth: number
  processingSpeed: number
}

// State
const payslips = ref<Payslip[]>([])
const stats = ref<Statistics>({
  totalProcessed: 0,
  successRate: 0,
  avgProcessingTime: 0,
  avgConfidence: 0,
  highConfidence: 0,
  mediumConfidence: 0,
  lowConfidence: 0,
  monthlyGrowth: 0,
  processingSpeed: 0
})
const isLoading = ref(false)
const searchQuery = ref('')
const viewMode = ref<'list' | 'grid'>('list')
const expandedItems = ref<Set<number>>(new Set())
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Filters
interface Filters {
  status: string
  dateRange: string
  source: string
  confidenceLevel: string
  [key: string]: string // Index signature for dynamic access
}

const activeFilters = ref<Filters>({
  status: '',
  dateRange: 'month',
  source: '',
  confidenceLevel: ''
})

const statusOptions = [
  { value: '', label: 'All Statuses', color: 'bg-gray-400' },
  { value: 'completed', label: 'Completed', color: 'bg-green-500' },
  { value: 'processing', label: 'Processing', color: 'bg-blue-500' },
  { value: 'queued', label: 'Queued', color: 'bg-yellow-500' },
  { value: 'failed', label: 'Failed', color: 'bg-red-500' }
]

// Computed
const hasActiveFilters = computed(() => {
  return Object.values(activeFilters.value).some(v => v && v !== 'month')
})

const dateRangeLabel = computed(() => {
  const labels: Record<string, string> = {
    today: 'Today',
    week: 'This Week',
    month: 'This Month',
    quarter: 'This Quarter',
    year: 'This Year',
    all: 'All Time'
  }
  return labels[activeFilters.value.dateRange] || 'This Month'
})

const filteredPayslips = computed(() => {
  let filtered = [...payslips.value]

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(query) ||
      p.extracted_data?.nama?.toLowerCase().includes(query) ||
      p.extracted_data?.no_gaji?.toLowerCase().includes(query) ||
      p.extracted_data?.bulan?.toLowerCase().includes(query)
    )
  }

  // Status filter
  if (activeFilters.value.status) {
    filtered = filtered.filter(p => p.status === activeFilters.value.status)
  }

  // Date range filter
  if (activeFilters.value.dateRange !== 'all') {
    const now = new Date()
    const ranges: Record<string, Date> = {
      today: new Date(now.setHours(0, 0, 0, 0)),
      week: new Date(now.setDate(now.getDate() - 7)),
      month: new Date(now.setMonth(now.getMonth() - 1)),
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
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage.value, filteredPayslips.value.length))

const visiblePageNumbers = computed(() => {
  const total = totalPages.value
  const current = currentPage.value
  const delta = 2
  const range: number[] = []
  const rangeWithDots: (number | string)[] = []
  let l: number | undefined

  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
      range.push(i)
    }
  }

  range.forEach((i) => {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1)
      } else if (i - l !== 1) {
        rangeWithDots.push('...')
      }
    }
    rangeWithDots.push(i)
    l = i
  })

  return rangeWithDots
})

// Methods
const debouncedSearch = (() => {
  let timeout: ReturnType<typeof setTimeout> | null = null
  return () => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => {
      currentPage.value = 1
    }, 300)
  }
})()

const setFilter = (key: string, value: any) => {
  activeFilters.value[key] = activeFilters.value[key] === value ? '' : value
  currentPage.value = 1
}

const removeFilter = (key: string) => {
  activeFilters.value[key] = ''
  currentPage.value = 1
}

const clearAllFilters = () => {
  activeFilters.value = {
    status: '',
    dateRange: 'month',
    source: '',
    confidenceLevel: ''
  }
  searchQuery.value = ''
  currentPage.value = 1
}

const getFilterLabel = (key: string, value: string) => {
  const labels: Record<string, Record<string, string>> = {
    status: {
      completed: 'Completed',
      processing: 'Processing',
      queued: 'Queued',
      failed: 'Failed'
    },
    dateRange: {
      today: 'Today',
      week: 'This Week',
      month: 'This Month',
      quarter: 'This Quarter',
      year: 'This Year'
    }
  }
  return labels[key]?.[value] || value
}

const toggleExpanded = (id: number) => {
  if (expandedItems.value.has(id)) {
    expandedItems.value.delete(id)
  } else {
    expandedItems.value.add(id)
  }
}

const goToPage = (page: number | string) => {
  if (typeof page === 'number' && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
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
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const data = await response.json()
    console.log('API Response:', data) // Debug logging
    
    // Handle different response structures
    let payslipsData = []
    if (Array.isArray(data)) {
      payslipsData = data
    } else if (data.payslips) {
      payslipsData = data.payslips
    } else if (data.data) {
      payslipsData = data.data
    } else {
      payslipsData = []
    }
    
    console.log('Processed payslips data:', payslipsData) // Debug logging
    
    // Transform the data to match our interface
    payslips.value = payslipsData.map((p: any) => ({
      id: p.id,
      name: p.file_path ? p.file_path.split('/').pop() || 'Unknown File' : 'Unknown File',
      status: p.status,
      size: p.file_size || 0,
      source: p.source,
      created_at: p.created_at,
      processing_completed_at: p.processing_completed_at,
      processing_error: p.processing_error,
      extracted_data: p.extracted_data,
      quality_metrics: p.quality_metrics,
      koperasi_summary: p.koperasi_summary
    }))
    
    console.log('Final payslips array:', payslips.value) // Debug logging
    
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
  
  const highConf = payslips.value.filter(p => (p.quality_metrics?.confidence_score || 0) >= 80).length
  const medConf = payslips.value.filter(p => {
    const score = p.quality_metrics?.confidence_score || 0
    return score >= 60 && score < 80
  }).length
  const lowConf = payslips.value.filter(p => (p.quality_metrics?.confidence_score || 0) < 60).length

  stats.value = {
    totalProcessed: total,
    successRate: total > 0 ? Math.round((completed / total) * 100) : 0,
    avgProcessingTime: total > 0 ? Math.round(totalProcessingTime / total) : 0,
    avgConfidence: total > 0 ? Math.round(totalConfidence / total) : 0,
    highConfidence: highConf,
    mediumConfidence: medConf,
    lowConfidence: lowConf,
    monthlyGrowth: 23, // Mock data - calculate from actual data
    processingSpeed: 15 // Mock data - calculate from actual data
  }
}

const handleView = (payslip: Payslip) => {
  // Handle view action
  console.log('View payslip:', payslip.id)
}

const handleReprocess = async (payslip: Payslip) => {
  try {
    const response = await fetch(`/api/payslips/${payslip.id}/reprocess`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
      }
    })
    if (response.ok) {
      await loadPayslips()
    }
  } catch (error) {
    console.error('Failed to reprocess payslip:', error)
  }
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

const handleDownload = (payslip: Payslip) => {
  // Handle download action
  console.log('Download payslip:', payslip.id)
}

// Utility functions
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

const formatShortCurrency = (amount?: number): string => {
  if (!amount) return 'RM 0'
  if (amount >= 1000000) {
    return `RM ${(amount / 1000000).toFixed(1)}M`
  }
  if (amount >= 1000) {
    return `RM ${(amount / 1000).toFixed(1)}k`
  }
  return `RM ${amount.toLocaleString('en-MY')}`
}

const formatProcessingTime = (seconds?: number): string => {
  if (!seconds) return 'N/A'
  if (seconds < 60) return `${seconds.toFixed(1)}s`
  if (seconds < 3600) return `${(seconds / 60).toFixed(1)}m`
  return `${(seconds / 3600).toFixed(1)}h`
}

// Lifecycle
onMounted(() => {
  loadPayslips()
  
  // Keyboard shortcuts
  const handleKeydown = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault()
      document.querySelector<HTMLInputElement>('input[type="text"]')?.focus()
    }
  }
  document.addEventListener('keydown', handleKeydown)
  return () => document.removeEventListener('keydown', handleKeydown)
})

// Expose methods for parent component
defineExpose({
  loadPayslips
})
</script> 