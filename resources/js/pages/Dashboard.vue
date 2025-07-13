<template>
    <Head title="AI Processing Dashboard" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Enhanced Header with AI Branding -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">AI Processing Dashboard</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-muted-foreground">Intelligent payslip processing powered by Google Vision API</p>
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
            </div>

            <!-- Enhanced AI Processing Statistics -->
            <PermissionGuard permission="payslip.view">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-sm">Total Processed</p>
                                    <p class="text-2xl font-bold">{{ statistics?.stats?.total || 0 }}</p>
                                    <p class="text-blue-200 text-xs mt-1">by AI</p>
                                </div>
                                <div class="p-2 bg-blue-400/20 rounded-full">
                                    <FileText class="h-6 w-6 text-blue-100" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-yellow-100 text-sm">AI Queue</p>
                                    <p class="text-2xl font-bold">{{ statistics?.stats?.queued || 0 }}</p>
                                    <p class="text-yellow-200 text-xs mt-1">awaiting processing</p>
                                </div>
                                <div class="p-2 bg-yellow-400/20 rounded-full">
                                    <Clock class="h-6 w-6 text-yellow-100" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-orange-500 to-orange-600 text-white">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-orange-100 text-sm">AI Processing</p>
                                    <p class="text-2xl font-bold">{{ statistics?.stats?.processing || 0 }}</p>
                                    <p class="text-orange-200 text-xs mt-1">in progress</p>
                                </div>
                                <div class="p-2 bg-orange-400/20 rounded-full">
                                    <LoaderCircle class="h-6 w-6 text-orange-100 animate-spin" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-green-500 to-green-600 text-white">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-sm">AI Completed</p>
                                    <p class="text-2xl font-bold">{{ statistics?.stats?.completed || 0 }}</p>
                                    <p class="text-green-200 text-xs mt-1">successfully</p>
                                </div>
                                <div class="p-2 bg-green-400/20 rounded-full">
                                    <CheckCircle class="h-6 w-6 text-green-100" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="bg-gradient-to-r from-red-500 to-red-600 text-white">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-red-100 text-sm">Failed</p>
                                    <p class="text-2xl font-bold">{{ statistics?.stats?.failed || 0 }}</p>
                                    <p class="text-red-200 text-xs mt-1">processing errors</p>
                                </div>
                                <div class="p-2 bg-red-400/20 rounded-full">
                                    <XCircle class="h-6 w-6 text-red-100" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </PermissionGuard>

            <!-- Upload Section -->
            <PermissionGuard permission="payslip.create">
                <!-- Enhanced Upload Mode Toggle -->
                <div class="flex items-center justify-center">
                    <div class="flex items-center space-x-1 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/50 dark:to-purple-950/50 p-1.5 rounded-lg border border-blue-200 dark:border-blue-800">
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="[
                                'px-4 py-2 rounded-md transition-all duration-200 h-8 text-sm font-medium',
                                uploadMode === 'single' ? 'bg-white shadow-sm text-blue-900 dark:bg-gray-900 dark:text-blue-100' : 'hover:bg-white/50 text-blue-700 dark:text-blue-300'
                            ]"
                            @click="uploadMode = 'single'"
                        >
                            <UploadCloud class="w-4 h-4 mr-2" />
                            Single AI Upload
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="[
                                'px-4 py-2 rounded-md transition-all duration-200 h-8 text-sm font-medium',
                                uploadMode === 'batch' ? 'bg-white shadow-sm text-blue-900 dark:bg-gray-900 dark:text-blue-100' : 'hover:bg-white/50 text-blue-700 dark:text-blue-300'
                            ]"
                            @click="uploadMode = 'batch'"
                        >
                            <Package class="w-4 h-4 mr-2" />
                            Batch AI Processing
                        </Button>
                    </div>
                </div>

                <!-- Single Upload Mode -->
                <EnhancedFileUploader 
                    v-if="uploadMode === 'single'"
                    :max-file-size="10"
                    :allowed-file-types="['pdf', 'png', 'jpg', 'jpeg']"
                    :multiple="true"
                    @upload-complete="onUploadComplete"
                    @file-added="onFileAdded"
                    @file-removed="onFileRemoved"
                />

                <!-- Batch Upload Mode -->
                <BatchUploader v-else-if="uploadMode === 'batch'" @batch-uploaded="onBatchUploaded" />
            </PermissionGuard>

            <!-- Batch Monitor -->
            <PermissionGuard permission="payslip.view">
                <BatchMonitor v-if="uploadMode === 'batch'" />
            </PermissionGuard>

            <!-- Enhanced AI Processing Queue -->
            <PermissionGuard permission="queue.view">
                <Card v-if="queuedFiles.length > 0" class="bg-gradient-to-br from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 border-blue-200 dark:border-blue-800 shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="flex items-center gap-3 text-base font-semibold text-blue-900 dark:text-blue-100">
                                    <div class="p-2 bg-blue-500/10 rounded-lg">
                                        <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    AI Processing Queue
                                </CardTitle>
                                <CardDescription class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    Google Vision API is intelligently processing your payslips
                                </CardDescription>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" @click="refreshQueue" :disabled="isRefreshing" class="h-8 px-3 text-xs border-blue-200 hover:bg-blue-50 dark:border-blue-800 dark:hover:bg-blue-950/50">
                                    <RefreshCw :class="['h-3 w-3 mr-1.5', isRefreshing && 'animate-spin']" />
                                    Refresh
                                </Button>
                                <PermissionGuard permission="queue.manage">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="outline" size="sm" class="h-8 px-3 text-xs border-blue-200 hover:bg-blue-50 dark:border-blue-800 dark:hover:bg-blue-950/50">
                                                <Settings class="h-3 w-3 mr-1.5" />
                                                Manage
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="clearCompleted" :disabled="isClearing">
                                                <CheckCircle class="h-3 w-3 mr-2" />
                                                Clear AI Processed
                                            </DropdownMenuItem>
                                            <PermissionGuard permission="queue.clear">
                                                <DropdownMenuItem @click="clearAll" :disabled="isClearing" class="text-red-600">
                                                    <Trash2 class="h-3 w-3 mr-2" />
                                                    Clear Queue
                                                </DropdownMenuItem>
                                            </PermissionGuard>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </PermissionGuard>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Collapsible v-for="file in queuedFiles" :key="file.job_id" class="group">
                            <div class="bg-white dark:bg-gray-950 rounded-xl border border-blue-100 dark:border-blue-800 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-between p-4">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="relative p-2 bg-blue-50 dark:bg-blue-950/50 rounded-lg">
                                            <FileText class="h-4 w-4 text-blue-600" />
                                            <div class="absolute -top-1 -right-1 h-3 w-3 bg-blue-500 rounded-full flex items-center justify-center">
                                                <svg class="h-2 w-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate text-sm">{{ file.name }}</h3>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-xs text-gray-500">{{ (file.size / 1024).toFixed(1) }} KB</span>
                                                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                                <span class="text-xs text-blue-600 font-medium">AI Processing</span>
                                                <span class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                                <span class="text-xs text-gray-500" v-if="file.created_at">{{ new Date(file.created_at).toLocaleDateString() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span :class="[
                                            'px-3 py-1 text-xs font-medium rounded-full',
                                            file.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-200' :
                                            file.status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-200' :
                                            file.status === 'processing' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200' :
                                            'bg-gray-100 text-gray-700 dark:bg-gray-900/50 dark:text-gray-200'
                                        ]">
                                            {{ file.status === 'completed' ? 'AI Processed' : 
                                               file.status === 'processing' ? 'AI Processing' : 
                                               file.status === 'failed' ? 'Failed' : 'Queued' }}
                                        </span>
                                        <div class="flex gap-1">
                                            <CollapsibleTrigger asChild>
                                                <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900">
                                                    <ChevronDown class="h-4 w-4" />
                                                </Button>
                                            </CollapsibleTrigger>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50" @click="deletePayslip(file.id)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                
                                <CollapsibleContent>
                                    <div class="border-t border-blue-50 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/20">
                                        <!-- Error State -->
                                        <div v-if="file.status === 'failed' && file.data?.error" class="p-4">
                                            <div class="flex items-start gap-3 p-3 bg-red-50 dark:bg-red-950/50 border border-red-100 dark:border-red-800 rounded-lg">
                                                <XCircle class="h-5 w-5 text-red-500 mt-0.5" />
                                                <div>
                                                    <h4 class="font-medium text-red-900 dark:text-red-100 text-sm">AI Processing Failed</h4>
                                                    <p class="text-red-700 dark:text-red-300 text-sm mt-1">{{ file.data.error }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Success State -->
                                        <div v-else-if="file.status === 'completed' && file.data" class="p-4 space-y-4">
                                            <!-- AI Extracted Data -->
                                            <div class="bg-white dark:bg-gray-950 rounded-lg p-4 border border-blue-100 dark:border-blue-800">
                                                <h4 class="font-semibold text-blue-900 dark:text-blue-100 text-sm mb-3 flex items-center gap-2">
                                                    <div class="p-1 bg-blue-500/10 rounded">
                                                        <svg class="h-3 w-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                    </div>
                                                    Google Vision API Extracted Data
                                                </h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div v-if="file.data.nama" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Employee Name</span>
                                                        <span class="font-medium text-blue-900 dark:text-blue-100 text-sm">{{ file.data.nama }}</span>
                                                    </div>
                                                    <div v-if="file.data.no_gaji" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Employee ID</span>
                                                        <span class="font-mono text-blue-900 dark:text-blue-100 text-sm">{{ file.data.no_gaji }}</span>
                                                    </div>
                                                    <div v-if="file.data.bulan" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Period</span>
                                                        <span class="font-mono text-blue-900 dark:text-blue-100 text-sm">{{ file.data.bulan }}</span>
                                                    </div>
                                                    <div v-if="file.data.gaji_pokok" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Basic Salary</span>
                                                        <span class="font-mono font-medium text-blue-900 dark:text-blue-100 text-sm">RM {{ file.data.gaji_pokok?.toLocaleString() }}</span>
                                                    </div>
                                                    <div v-if="file.data.jumlah_pendapatan" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Total Income</span>
                                                        <span class="font-mono font-medium text-green-600 dark:text-green-400 text-sm">RM {{ file.data.jumlah_pendapatan?.toLocaleString() }}</span>
                                                    </div>
                                                    <div v-if="file.data.jumlah_potongan" class="flex justify-between items-center py-2 border-b border-blue-50 dark:border-blue-900">
                                                        <span class="text-blue-600 dark:text-blue-400 text-sm">Total Deductions</span>
                                                        <span class="font-mono font-medium text-red-600 dark:text-red-400 text-sm">RM {{ file.data.jumlah_potongan?.toLocaleString() }}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Net Salary Highlight -->
                                                <div v-if="file.data.gaji_bersih" class="mt-4 p-3 bg-gradient-to-r from-green-50 to-blue-50 dark:from-green-950/50 dark:to-blue-950/50 rounded-lg border border-green-100 dark:border-green-800">
                                                    <div class="flex justify-between items-center">
                                                        <span class="font-medium text-green-900 dark:text-green-100">AI Detected Net Salary</span>
                                                        <span class="font-bold text-lg text-green-900 dark:text-green-100">RM {{ file.data.gaji_bersih?.toLocaleString() }}</span>
                                                    </div>
                                                    <div class="flex justify-between items-center mt-1">
                                                        <span class="text-green-700 dark:text-green-300 text-sm">Salary Percentage</span>
                                                        <span class="font-bold text-green-700 dark:text-green-300">{{ file.data.peratus_gaji_bersih ?? 'N/A' }}%</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- AI Koperasi Eligibility -->
                                            <div class="bg-white dark:bg-gray-950 rounded-lg p-4 border border-purple-100 dark:border-purple-800">
                                                <h4 class="font-semibold text-purple-900 dark:text-purple-100 text-sm mb-3 flex items-center gap-2">
                                                    <div class="p-1 bg-purple-500/10 rounded">
                                                        <svg class="h-3 w-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                    </div>
                                                    AI Koperasi Eligibility Analysis
                                                </h4>
                                                <div v-if="!file.data.koperasi_results || Object.keys(file.data.koperasi_results).length === 0" class="text-center py-4 text-purple-500 dark:text-purple-400">
                                                    <div class="p-2 bg-purple-100 dark:bg-purple-950/50 rounded-lg inline-block mb-2">
                                                        <XCircle class="h-5 w-5 text-purple-400" />
                                                    </div>
                                                    <p class="text-sm">No active koperasi available for AI analysis</p>
                                                </div>
                                                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                                    <div v-for="(isEligible, name) in file.data.koperasi_results" :key="name" 
                                                         :class="[
                                                             'flex items-center justify-between p-2 rounded-lg border text-xs',
                                                             isEligible 
                                                                 ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-950/50 dark:border-green-800 dark:text-green-200' 
                                                                 : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-950/50 dark:border-red-800 dark:text-red-200'
                                                         ]">
                                                        <span class="font-medium truncate mr-2">{{ name.replace('Koperasi ', '') }}</span>
                                                        <span :class="[
                                                            'flex-shrink-0 w-2 h-2 rounded-full',
                                                            isEligible ? 'bg-green-500' : 'bg-red-500'
                                                        ]"></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- AI Debug Info (Collapsible) -->
                                            <div v-if="file.data.debug_info" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                                <details class="group">
                                                    <summary class="cursor-pointer text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 text-sm font-medium flex items-center gap-2">
                                                        <ChevronDown class="h-3 w-3 transition-transform group-open:rotate-180" />
                                                        Google Vision API Debug Information
                                                    </summary>
                                                    <div class="mt-3 space-y-2 text-xs">
                                                        <div v-if="file.data.debug_info.text_length" class="flex justify-between">
                                                            <span class="text-gray-600 dark:text-gray-400">OCR Text Length</span>
                                                            <span class="font-mono text-gray-900 dark:text-gray-100">{{ file.data.debug_info.text_length }} characters</span>
                                                        </div>
                                                        <div v-if="file.data.debug_info.extraction_patterns_found" class="space-y-1">
                                                            <span class="text-gray-600 dark:text-gray-400">AI Extraction Patterns Found:</span>
                                                            <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 ml-4">
                                                                <li v-for="pattern in file.data.debug_info.extraction_patterns_found" :key="pattern">{{ pattern }}</li>
                                                            </ul>
                                                        </div>
                                                        <div v-if="file.data.debug_info.confidence_scores" class="space-y-1">
                                                            <span class="text-gray-600 dark:text-gray-400">AI Confidence Scores:</span>
                                                            <div class="grid grid-cols-2 gap-2 mt-2">
                                                                <div v-for="(score, field) in file.data.debug_info.confidence_scores" :key="field" class="flex justify-between">
                                                                    <span class="text-gray-600 dark:text-gray-400">{{ field }}</span>
                                                                    <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ score }}%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </details>
                                            </div>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>
                    </CardContent>
                </Card>
            </PermissionGuard>

            <!-- Permission Denied Cards -->
            <PermissionGuard permission="queue.view" fallback>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="p-2 bg-muted rounded-full">
                                <Shield class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">Queue Access Restricted</h3>
                                <p class="text-muted-foreground text-xs">You don't have permission to view the processing queue.</p>
                                <p class="text-xs text-muted-foreground mt-1">Contact your administrator for access.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>

            <PermissionGuard permission="payslip.view" fallback>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="p-2 bg-muted rounded-full">
                                <Shield class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold">Access Restricted</h3>
                                <p class="text-muted-foreground text-xs">You don't have permission to view payslip data.</p>
                                <p class="text-xs text-muted-foreground mt-1">Contact your administrator for access.</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </PermissionGuard>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import PermissionGuard from '@/components/PermissionGuard.vue'
import BatchUploader from '@/components/BatchUploader.vue'
import BatchMonitor from '@/components/BatchMonitor.vue'
import EnhancedFileUploader from '@/components/EnhancedFileUploader.vue'
import { usePermissions } from '@/composables/usePermissions'
import { 
    UploadCloud, FileText, LoaderCircle, XCircle, ChevronDown, 
    Clock, CheckCircle, List, RefreshCw, Settings, Shield, Package, Trash2
} from 'lucide-vue-next'
import { ref, onMounted, onUnmounted } from 'vue'

interface FileResponse {
    job_id: string
    id: number
    name: string
    size: number
    status: string
    created_at?: string
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
        debug_info?: {
            text_length?: number
            extraction_patterns_found?: string[]
            confidence_scores?: Record<string, number>
        }
        error?: string
    }
}

interface QueueStats {
    total: number
    queued: number
    processing: number
    completed: number
    failed: number
}

interface Statistics {
    stats: QueueStats
}

const { hasPermission } = usePermissions()
const uploadMode = ref<'single' | 'batch'>('single')
const queuedFiles = ref<FileResponse[]>([])
const statistics = ref<Statistics | null>(null)
const isRefreshing = ref(false)
const isClearing = ref(false)

let refreshInterval: number | null = null

const loadStatistics = async () => {
    try {
        const response = await fetch('/api/queue/statistics')
        if (response.ok) {
            const data = await response.json()
            statistics.value = data
        }
    } catch (error) {
        console.error('Failed to load statistics:', error)
    }
}

const loadQueue = async () => {
    try {
        const response = await fetch('/api/queue')
        if (response.ok) {
            const data = await response.json()
            queuedFiles.value = data.files || []
        }
    } catch (error) {
        console.error('Failed to load queue:', error)
    }
}

const refreshQueue = async () => {
    isRefreshing.value = true
    try {
        await Promise.all([loadQueue(), loadStatistics()])
    } finally {
        isRefreshing.value = false
    }
}

const clearCompleted = async () => {
    isClearing.value = true
    try {
        const response = await fetch('/api/queue/clear-completed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        if (response.ok) {
            await refreshQueue()
        }
    } catch (error) {
        console.error('Failed to clear completed:', error)
    } finally {
        isClearing.value = false
    }
}

const clearAll = async () => {
    if (!confirm('Are you sure you want to clear all items from the queue?')) return
    
    isClearing.value = true
    try {
        const response = await fetch('/api/queue/clear', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        if (response.ok) {
            await refreshQueue()
        }
    } catch (error) {
        console.error('Failed to clear queue:', error)
    } finally {
        isClearing.value = false
    }
}

const deletePayslip = async (id: number) => {
    if (!confirm('Are you sure you want to delete this payslip?')) return
    
    try {
        const response = await fetch(`/api/payslips/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        if (response.ok) {
            await refreshQueue()
        }
    } catch (error) {
        console.error('Failed to delete payslip:', error)
    }
}

const onUploadComplete = (results: any[]) => {
    console.log('Upload complete:', results)
    refreshQueue()
}

const onFileAdded = (file: File) => {
    console.log('File added:', file.name)
}

const onFileRemoved = (fileId: string) => {
    console.log('File removed:', fileId)
}

const onBatchUploaded = (result: any) => {
    console.log('Batch uploaded:', result)
    refreshQueue()
}

onMounted(() => {
    if (hasPermission('queue.view')) {
        loadQueue()
        loadStatistics()
        
        // Set up auto-refresh every 5 seconds
        refreshInterval = setInterval(() => {
            refreshQueue()
        }, 5000)
    }
})

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval)
    }
})
</script>
