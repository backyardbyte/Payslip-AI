<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-6xl max-h-[90vh] overflow-auto">
      <DialogHeader>
        <DialogTitle class="text-xl font-semibold flex items-center gap-3">
          <div class="p-2 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full">
            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          Google Vision API Processing Results
        </DialogTitle>
        <DialogDescription class="text-sm">
          Comprehensive analysis results for {{ payslip?.name }}
          <div class="flex items-center gap-2 mt-2">
            <span class="inline-flex items-center gap-1.5 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full dark:bg-blue-900/50 dark:text-blue-200">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
              </svg>
              Google Vision API
            </span>
            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full dark:bg-green-900/50 dark:text-green-200">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              AI Processed
            </span>
          </div>
        </DialogDescription>
      </DialogHeader>

      <div v-if="payslip" class="space-y-6 mt-6">
        <!-- AI Processing Summary -->
        <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
          <div class="flex items-center gap-4 mb-4">
            <div class="p-3 bg-blue-500/10 rounded-full">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">AI Processing Summary</h3>
              <p class="text-blue-700 dark:text-blue-300">Advanced document analysis powered by Google Cloud Vision API</p>
            </div>
          </div>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">{{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%</div>
              <div class="text-sm text-blue-800 dark:text-blue-200 font-medium">AI Confidence</div>
              <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">Extraction accuracy</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-green-600">{{ Math.round(payslip.quality_metrics?.data_completeness || 0) }}%</div>
              <div class="text-sm text-green-800 dark:text-green-200 font-medium">Data Complete</div>
              <div class="text-xs text-green-600 dark:text-green-400 mt-1">Information extracted</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-purple-600">{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</div>
              <div class="text-sm text-purple-800 dark:text-purple-200 font-medium">Processing Time</div>
              <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">Google Vision API</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-orange-600">{{ payslip.koperasi_summary?.eligible_count || 0 }}/{{ payslip.koperasi_summary?.total_checked || 0 }}</div>
              <div class="text-sm text-orange-800 dark:text-orange-200 font-medium">Eligible</div>
              <div class="text-xs text-orange-600 dark:text-orange-400 mt-1">Koperasi analysis</div>
            </div>
          </div>
        </div>

        <!-- AI Extracted Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Employee Information -->
          <Card>
            <CardHeader>
              <CardTitle class="text-lg flex items-center gap-2">
                <div class="p-2 bg-blue-500/10 rounded-lg">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </div>
                Employee Information
              </CardTitle>
              <CardDescription>Personal and employment details extracted by AI</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-3">
                <div class="flex items-center justify-between py-3 border-b border-muted">
                  <span class="text-sm font-medium text-muted-foreground">Employee Name</span>
                  <span class="text-sm font-semibold">{{ payslip.data?.nama || 'Not extracted' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted">
                  <span class="text-sm font-medium text-muted-foreground">Employee ID</span>
                  <span class="text-sm font-mono">{{ payslip.data?.no_gaji || 'Not extracted' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.ic_number">
                  <span class="text-sm font-medium text-muted-foreground">IC Number</span>
                  <span class="text-sm font-mono">{{ payslip.data.ic_number }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted">
                  <span class="text-sm font-medium text-muted-foreground">Pay Period</span>
                  <span class="text-sm font-mono">{{ payslip.data?.bulan || 'Not extracted' }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.position">
                  <span class="text-sm font-medium text-muted-foreground">Position</span>
                  <span class="text-sm">{{ payslip.data.position }}</span>
                </div>
                <div class="flex items-center justify-between py-3" v-if="payslip.data?.peratus_gaji_bersih">
                  <span class="text-sm font-medium text-muted-foreground">Salary Percentage</span>
                  <span class="text-sm font-bold text-blue-600">{{ payslip.data.peratus_gaji_bersih }}%</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Department & Bank Information -->
          <Card>
            <CardHeader>
              <CardTitle class="text-lg flex items-center gap-2">
                <div class="p-2 bg-purple-500/10 rounded-lg">
                  <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                Department & Banking Details
              </CardTitle>
              <CardDescription>Organizational and financial account information</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="space-y-3">
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.department">
                  <span class="text-sm font-medium text-muted-foreground">Department</span>
                  <span class="text-sm font-semibold">{{ payslip.data.department }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.department_code">
                  <span class="text-sm font-medium text-muted-foreground">Department Code</span>
                  <span class="text-sm font-mono">{{ payslip.data.department_code }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.kump_ptj">
                  <span class="text-sm font-medium text-muted-foreground">Kump PTJ/PTJ</span>
                  <span class="text-sm font-mono">{{ payslip.data.kump_ptj }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.pusat_pembayar">
                  <span class="text-sm font-medium text-muted-foreground">Pusat Pembayar</span>
                  <span class="text-sm">{{ payslip.data.pusat_pembayar }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.cukai_kwsp">
                  <span class="text-sm font-medium text-muted-foreground">No Cukai/KWSP</span>
                  <span class="text-sm font-mono">{{ payslip.data.cukai_kwsp }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-muted" v-if="payslip.data?.bank_name">
                  <span class="text-sm font-medium text-muted-foreground">Bank</span>
                  <span class="text-sm font-mono">{{ payslip.data.bank_name }}</span>
                </div>
                <div class="flex items-center justify-between py-3" v-if="payslip.data?.bank_account">
                  <span class="text-sm font-medium text-muted-foreground">Account Number</span>
                  <span class="text-sm font-mono">{{ payslip.data.bank_account }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Salary Breakdown Summary -->
        <Card>
          <CardHeader>
            <CardTitle class="text-lg flex items-center gap-2">
              <div class="p-2 bg-green-500/10 rounded-lg">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                </svg>
              </div>
              Salary Summary
            </CardTitle>
            <CardDescription>Financial breakdown detected by AI</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div class="text-center p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(payslip.data?.gaji_pokok) }}</div>
                <div class="text-sm text-blue-800 dark:text-blue-200 font-medium">Basic Salary</div>
                <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">Gaji Pokok</div>
              </div>
              <div class="text-center p-4 bg-green-50 dark:bg-green-950/20 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ formatCurrency(payslip.data?.jumlah_pendapatan) }}</div>
                <div class="text-sm text-green-800 dark:text-green-200 font-medium">Total Earnings</div>
                <div class="text-xs text-green-600 dark:text-green-400 mt-1">Jumlah Pendapatan</div>
              </div>
              <div class="text-center p-4 bg-red-50 dark:bg-red-950/20 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ formatCurrency(payslip.data?.jumlah_potongan) }}</div>
                <div class="text-sm text-red-800 dark:text-red-200 font-medium">Total Deductions</div>
                <div class="text-xs text-red-600 dark:text-red-400 mt-1">Jumlah Potongan</div>
              </div>
              <div class="text-center p-4 bg-emerald-50 dark:bg-emerald-950/20 rounded-lg border-2 border-emerald-200 dark:border-emerald-800">
                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ formatCurrency(payslip.data?.gaji_bersih) }}</div>
                <div class="text-sm text-emerald-800 dark:text-emerald-200 font-medium">Net Salary</div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">Gaji Bersih</div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Detailed Earnings and Deductions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" v-if="payslip.data?.individual_earnings || payslip.data?.individual_deductions">
          <!-- Individual Earnings -->
          <Card v-if="payslip.data?.individual_earnings && payslip.data.individual_earnings.length > 0">
            <CardHeader>
              <CardTitle class="text-lg flex items-center gap-2">
                <div class="p-2 bg-green-500/10 rounded-lg">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                  </svg>
                </div>
                Detailed Earnings (Pendapatan)
              </CardTitle>
              <CardDescription>Individual earning components</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div 
                v-for="earning in payslip.data.individual_earnings" 
                :key="earning.code"
                class="flex items-center justify-between py-2 px-3 bg-green-50 dark:bg-green-950/20 rounded-lg"
              >
                <div class="flex-1">
                  <div class="text-sm font-medium text-green-900 dark:text-green-100">{{ earning.description }}</div>
                  <div class="text-xs text-green-700 dark:text-green-300">Code: {{ earning.code }}</div>
                </div>
                <div class="text-sm font-semibold text-green-700 dark:text-green-300">
                  {{ formatCurrency(earning.amount) }}
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Individual Deductions -->
          <Card v-if="payslip.data?.individual_deductions && payslip.data.individual_deductions.length > 0">
            <CardHeader>
              <CardTitle class="text-lg flex items-center gap-2">
                <div class="p-2 bg-red-500/10 rounded-lg">
                  <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                  </svg>
                </div>
                Detailed Deductions (Potongan)
              </CardTitle>
              <CardDescription>Individual deduction components</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div 
                v-for="deduction in payslip.data.individual_deductions" 
                :key="deduction.code"
                class="flex items-center justify-between py-2 px-3 bg-red-50 dark:bg-red-950/20 rounded-lg"
              >
                <div class="flex-1">
                  <div class="text-sm font-medium text-red-900 dark:text-red-100">{{ deduction.description }}</div>
                  <div class="text-xs text-red-700 dark:text-red-300">Code: {{ deduction.code }}</div>
                </div>
                <div class="text-sm font-semibold text-red-700 dark:text-red-300">
                  {{ formatCurrency(deduction.amount) }}
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- AI Koperasi Eligibility Analysis -->
        <Card v-if="payslip.data?.koperasi_results && Object.keys(payslip.data.koperasi_results).length > 0">
          <CardHeader>
            <CardTitle class="text-lg flex items-center gap-2">
              <div class="p-2 bg-purple-500/10 rounded-lg">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              AI Koperasi Eligibility Analysis
            </CardTitle>
            <CardDescription>Automated eligibility assessment based on extracted salary data</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div
                v-for="(isEligible, koperasiName) in payslip.data.koperasi_results"
                :key="koperasiName"
                class="p-4 rounded-lg border transition-all duration-200 hover:shadow-md"
                :class="isEligible 
                  ? 'bg-green-50 border-green-200 hover:bg-green-100 dark:bg-green-950/20 dark:border-green-800' 
                  : 'bg-red-50 border-red-200 hover:bg-red-100 dark:bg-red-950/20 dark:border-red-800'"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="font-semibold text-sm" :class="isEligible ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100'">
                      {{ koperasiName }}
                    </h4>
                    <p class="text-xs mt-1" :class="isEligible ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                      {{ isEligible ? 'Eligible for loan' : 'Not eligible' }}
                    </p>
                  </div>
                  <div class="flex items-center">
                    <div 
                      class="w-8 h-8 rounded-full flex items-center justify-center"
                      :class="isEligible ? 'bg-green-100 dark:bg-green-900/50' : 'bg-red-100 dark:bg-red-900/50'"
                    >
                      <svg v-if="isEligible" class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                      <svg v-else class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Processing Metadata -->
        <Card>
          <CardHeader>
            <CardTitle class="text-lg flex items-center gap-2">
              <div class="p-2 bg-orange-500/10 rounded-lg">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              Processing Metadata & Quality Metrics
            </CardTitle>
            <CardDescription>Technical details about the Google Vision API processing</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <!-- Processing Summary -->
              <div class="space-y-3">
                <h4 class="font-semibold text-sm flex items-center gap-2">
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                  Processing Information
                </h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Processing Method</span>
                    <span class="font-medium">PDF.co + Google Vision</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Status</span>
                    <Badge :variant="payslip.status === 'completed' ? 'default' : 'destructive'">
                      {{ payslip.status === 'completed' ? 'Successfully Processed' : 'Processing Failed' }}
                    </Badge>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Upload Source</span>
                    <span class="font-medium">{{ getSourceLabel(payslip.source) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">File Size</span>
                    <span class="font-medium">{{ formatFileSize(payslip.size) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Text Extracted</span>
                    <span class="font-medium">{{ payslip.data?.debug_info?.text_length || 0 }} characters</span>
                  </div>
                </div>
              </div>
              
              <!-- Quality Metrics -->
              <div class="space-y-3">
                <h4 class="font-semibold text-sm flex items-center gap-2">
                  <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Quality Metrics
                </h4>
                <div class="space-y-3">
                  <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                      <span class="text-muted-foreground">AI Confidence</span>
                      <span class="font-medium">{{ Math.round(payslip.quality_metrics?.confidence_score || 0) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                        :style="{ width: `${Math.round(payslip.quality_metrics?.confidence_score || 0)}%` }"
                      ></div>
                    </div>
                  </div>
                  <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                      <span class="text-muted-foreground">Data Completeness</span>
                      <span class="font-medium">{{ Math.round(payslip.quality_metrics?.data_completeness || 0) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        class="bg-green-600 h-2 rounded-full transition-all duration-300"
                        :style="{ width: `${Math.round(payslip.quality_metrics?.data_completeness || 0)}%` }"
                      ></div>
                    </div>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Processing Time</span>
                    <span class="font-medium">{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</span>
                  </div>
                </div>
              </div>
              
              <!-- Timestamps -->
              <div class="space-y-3">
                <h4 class="font-semibold text-sm flex items-center gap-2">
                  <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Timeline
                </h4>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Uploaded</span>
                    <span class="font-medium">{{ formatDate(payslip.created_at) }}</span>
                  </div>
                  <div class="flex justify-between" v-if="payslip.processing_completed_at">
                    <span class="text-muted-foreground">AI Processed</span>
                    <span class="font-medium">{{ formatDate(payslip.processing_completed_at) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-muted-foreground">Total Duration</span>
                    <span class="font-medium">{{ formatProcessingTime(payslip.quality_metrics?.processing_time) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- AI Extraction Patterns (Debug Information) -->
        <Card v-if="payslip.data?.debug_info?.extraction_patterns_found && payslip.data.debug_info.extraction_patterns_found.length > 0">
          <CardHeader>
            <CardTitle class="text-lg flex items-center gap-2">
              <div class="p-2 bg-gray-500/10 rounded-lg">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
              </div>
              AI Extraction Patterns & Confidence Scores
            </CardTitle>
            <CardDescription>Technical details about how the AI extracted each field</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Extraction Patterns -->
              <div v-if="payslip.data.debug_info.extraction_patterns_found" class="space-y-2">
                <h4 class="font-semibold text-sm">Extraction Patterns Found</h4>
                <div class="bg-muted/50 rounded-lg p-3 max-h-48 overflow-y-auto">
                  <ul class="space-y-1 text-sm text-muted-foreground">
                    <li v-for="pattern in payslip.data.debug_info.extraction_patterns_found" :key="pattern" class="flex items-start gap-2">
                      <svg class="w-3 h-3 text-green-600 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                      </svg>
                      {{ pattern }}
                    </li>
                  </ul>
                </div>
              </div>
              
              <!-- Confidence Scores -->
              <div v-if="payslip.data.debug_info.confidence_scores" class="space-y-2">
                <h4 class="font-semibold text-sm">AI Field Confidence Scores</h4>
                <div class="space-y-3 max-h-48 overflow-y-auto">
                  <div 
                    v-for="(score, field) in payslip.data.debug_info.confidence_scores" 
                    :key="field"
                    class="space-y-1"
                  >
                    <div class="flex items-center justify-between text-sm">
                      <span class="text-muted-foreground capitalize font-medium">{{ field.replace(/_/g, ' ') }}</span>
                      <span class="font-mono font-medium">{{ score }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                      <div 
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="score >= 90 ? 'bg-green-600' : score >= 70 ? 'bg-yellow-600' : 'bg-red-600'"
                        :style="{ width: `${score}%` }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
        <Button variant="outline" @click="$emit('update:open', false)">
          Close
        </Button>
        <Button @click="downloadReport" class="bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Download AI Report
        </Button>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

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
  debug_info?: {
    text_length?: number
    extraction_patterns_found?: string[]
    confidence_scores?: Record<string, number>
  }
  ic_number?: string
  position?: string
  department?: string
  department_code?: string
  kump_ptj?: string
  pusat_pembayar?: string
  cukai_kwsp?: string
  bank_name?: string
  bank_account?: string
  individual_earnings?: {
    code: string
    description: string
    amount: number
  }[]
  individual_deductions?: {
    code: string
    description: string
    amount: number
  }[]
}

interface Payslip {
  id: number
  name: string
  status: 'completed' | 'failed' | 'processing' | 'queued'
  size: number
  source?: string
  created_at: string
  processing_completed_at?: string
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

interface Props {
  open: boolean
  payslip?: Payslip | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const formatCurrency = (amount?: number) => {
  if (amount === null || amount === undefined) return 'N/A'
  return new Intl.NumberFormat('ms-MY', {
    style: 'currency',
    currency: 'MYR'
  }).format(amount)
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatProcessingTime = (seconds?: number) => {
  if (!seconds) return 'N/A'
  return `${seconds.toFixed(1)}s`
}

const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const getSourceLabel = (source?: string) => {
  const labels = {
    'telegram': 'Telegram Bot',
    'whatsapp': 'WhatsApp Bot',
    'web': 'Web Application'
  }
  return labels[source as keyof typeof labels] || 'Web Application'
}

const downloadReport = () => {
  if (!props.payslip) return
  
  const reportData = {
    payslip_id: props.payslip.id,
    file_name: props.payslip.name,
    processing_method: 'Google Vision API',
    processing_summary: {
      status: props.payslip.status,
      confidence_score: props.payslip.quality_metrics?.confidence_score,
      data_completeness: props.payslip.quality_metrics?.data_completeness,
      processing_time: props.payslip.quality_metrics?.processing_time,
      upload_source: getSourceLabel(props.payslip.source)
    },
    extracted_data: props.payslip.data,
    koperasi_analysis: props.payslip.data?.koperasi_results,
    timestamps: {
      uploaded: props.payslip.created_at,
      processed: props.payslip.processing_completed_at
    },
    debug_information: props.payslip.data?.debug_info
  }
  
  const blob = new Blob([JSON.stringify(reportData, null, 2)], { type: 'application/json' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `ai-payslip-report-${props.payslip.id}-${new Date().toISOString().split('T')[0]}.json`
  a.click()
  window.URL.revokeObjectURL(url)
}
</script> 