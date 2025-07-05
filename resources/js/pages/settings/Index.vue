<template>
    <Head title="Settings" />
    
    <AppLayout>
        <template v-if="canManageSettings">
            <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold">System Settings</h1>
                    <p class="text-muted-foreground">Configure your Payslip AI system preferences</p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="resetToDefaults" :disabled="isResetting">
                        <RotateCcw :class="['h-4 w-4 mr-2', isResetting && 'animate-spin']" />
                        {{ isResetting ? 'Resetting...' : 'Reset to Defaults' }}
                    </Button>
                    <Button size="sm" @click="saveSettings" :disabled="isSaving">
                        <Save :class="['h-4 w-4 mr-2', isSaving && 'animate-pulse']" />
                        {{ isSaving ? 'Saving...' : 'Save Settings' }}
                    </Button>
                </div>
            </div>

                <!-- System Status Cards -->
                <PermissionGuard permission="system.view_health">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Card>
                            <CardContent class="p-4">
                                <div class="flex items-center space-x-2">
                                    <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/50">
                                        <Activity class="h-5 w-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">System Health</p>
                                        <p class="text-lg font-bold text-green-600">Healthy</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="p-4">
                                <div class="flex items-center space-x-2">
                                    <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/50">
                                        <Cpu class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Queue Status</p>
                                        <p class="text-lg font-bold">{{ systemHealth.queueCount }} jobs</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="p-4">
                                <div class="flex items-center space-x-2">
                                    <div class="p-2 bg-purple-100 rounded-lg dark:bg-purple-900/50">
                                        <Database class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Storage Used</p>
                                        <p class="text-lg font-bold">{{ systemHealth.storageUsed }}MB</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </PermissionGuard>

                <!-- Settings Tabs -->
                <div class="flex-1 flex flex-col">
                    <div class="border-b border-border">
                        <nav class="-mb-px flex space-x-8">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    'py-2 px-1 border-b-2 font-medium text-sm',
                                    activeTab === tab.id
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-gray-300'
                                ]"
                            >
                                <component :is="tab.icon" class="h-4 w-4 mr-2 inline" />
                                {{ tab.name }}
                            </button>
                        </nav>
                    </div>

                    <div class="flex-1 py-6">
                        <!-- General Settings -->
                        <div v-if="activeTab === 'general'" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>General Configuration</CardTitle>
                                    <CardDescription>Basic system preferences and defaults</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label>System Name</Label>
                                            <input
                                                v-model="settings.general.systemName"
                                                type="text"
                                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                        <div>
                                            <Label>Default Language</Label>
                                            <select v-model="settings.general.defaultLanguage" class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm">
                                                <option value="en">English</option>
                                                <option value="ms">Bahasa Malaysia</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox v-model:checked="settings.general.enableNotifications" />
                                        <Label>Enable system notifications</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Checkbox v-model:checked="settings.general.autoCleanup" />
                                        <Label>Automatically cleanup old files (older than 30 days)</Label>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>File Processing</CardTitle>
                                    <CardDescription>Configure file upload and processing limits</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label>Max File Size (MB)</Label>
                                            <input
                                                v-model.number="settings.general.maxFileSize"
                                                type="number"
                                                min="1"
                                                max="100"
                                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                        <div>
                                            <Label>Concurrent Processing</Label>
                                            <input
                                                v-model.number="settings.general.concurrentProcessing"
                                                type="number"
                                                min="1"
                                                max="10"
                                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label>Allowed File Types</Label>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <div v-for="type in availableFileTypes" :key="type" class="flex items-center space-x-2">
                                                <Checkbox :checked="settings.general.allowedFileTypes.includes(type)" @update:checked="(checked: boolean) => toggleFileType(type, checked)" />
                                                <Label class="text-sm">{{ type.toUpperCase() }}</Label>
                                            </div>
                                        </div>
                                        <p class="text-sm text-muted-foreground mt-1">Select which file types are allowed for upload</p>
                                        <!-- Debug info - remove in production -->
                                        <div class="mt-2 p-2 bg-gray-100 dark:bg-gray-800 rounded text-xs">
                                            <strong>Debug:</strong> Current allowedFileTypes: {{ JSON.stringify(settings.general.allowedFileTypes) }}
                                            <br><strong>Available types:</strong> {{ JSON.stringify(availableFileTypes) }}
                                            <br><strong>Checkbox states:</strong> 
                                            <span v-for="type in availableFileTypes" :key="type">
                                                {{ type }}={{ settings.general.allowedFileTypes.includes(type) }} 
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- OCR Settings -->
                        <div v-if="activeTab === 'ocr'" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>OCR Configuration</CardTitle>
                                    <CardDescription>Configure Tesseract OCR settings for better accuracy</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label>OCR Engine</Label>
                                            <select v-model="settings.ocr.engine" class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm">
                                                <option value="tesseract">Tesseract</option>
                                                <option value="paddleocr">PaddleOCR</option>
                                            </select>
                                        </div>
                                        <div>
                                            <Label>DPI Setting</Label>
                                            <select v-model="settings.ocr.dpi" class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm">
                                                <option value="150">150 DPI (Fast)</option>
                                                <option value="300">300 DPI (Balanced)</option>
                                                <option value="600">600 DPI (High Quality)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label>Languages</Label>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <div v-for="lang in ocrLanguages" :key="lang.code" class="flex items-center space-x-2">
                                                <Checkbox :checked="settings.ocr.languages.includes(lang.code)" @update:checked="(checked: boolean) => toggleLanguage(lang.code, checked)" />
                                                <Label class="text-sm">{{ lang.name }}</Label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.ocr.preprocessing" />
                                            <Label>Enable image preprocessing</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.ocr.autoRotate" />
                                            <Label>Auto-rotate images</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.ocr.enhanceContrast" />
                                            <Label>Enhance contrast</Label>
                                        </div>
                                    </div>

                                    <div>
                                        <Label>Confidence Threshold (%)</Label>
                                        <div class="mt-2 flex items-center space-x-4">
                                            <input
                                                v-model.number="settings.ocr.confidenceThreshold"
                                                type="range"
                                                min="10"
                                                max="100"
                                                class="flex-1"
                                            />
                                            <span class="text-sm font-mono w-10">{{ settings.ocr.confidenceThreshold }}%</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Pattern Recognition</CardTitle>
                                    <CardDescription>Configure pattern matching for Malaysian payslips</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <Label>Custom Patterns</Label>
                                        <div class="mt-2 space-y-2">
                                            <div v-for="(pattern, index) in settings.ocr.customPatterns" :key="index" 
                                                 class="flex items-center space-x-2">
                                                <input
                                                    v-model="pattern.name"
                                                    placeholder="Pattern name"
                                                    class="flex-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                />
                                                <input
                                                    v-model="pattern.regex"
                                                    placeholder="Regular expression"
                                                    class="flex-2 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                />
                                                <Button variant="outline" size="sm" @click="removePattern(index)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <Button variant="outline" size="sm" @click="addPattern">
                                                <Plus class="h-4 w-4 mr-2" />
                                                Add Pattern
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- API Settings -->
                        <div v-if="activeTab === 'api'" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>API Configuration</CardTitle>
                                    <CardDescription>Configure API keys and external service integrations</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <h3 class="text-lg font-medium mb-4">Rate Limiting</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label for="rateLimitPerMinute">Requests per minute</Label>
                                                <Input 
                                                    id="rateLimitPerMinute"
                                                    v-model="settings.api.rateLimitPerMinute" 
                                                    type="number" 
                                                    min="1" 
                                                    max="1000"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">Maximum API requests per minute per user/IP</p>
                                            </div>
                                            <div>
                                                <Label for="rateLimitPerHour">Requests per hour</Label>
                                                <Input 
                                                    id="rateLimitPerHour"
                                                    v-model="settings.api.rateLimitPerHour" 
                                                    type="number" 
                                                    min="1" 
                                                    max="10000"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">Maximum API requests per hour per user/IP</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="enableRateLimit"
                                                v-model:checked="settings.api.enableRateLimit"
                                            />
                                            <Label for="enableRateLimit">Enable API Rate Limiting</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="enableCors"
                                                v-model:checked="settings.api.enableCors"
                                            />
                                            <Label for="enableCors">Enable CORS</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="requireAuth"
                                                v-model:checked="settings.api.requireAuth"
                                            />
                                            <Label for="requireAuth">Require authentication for API access</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="logRequests"
                                                v-model:checked="settings.api.logRequests"
                                            />
                                            <Label for="logRequests">Log API requests</Label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Webhook Configuration</CardTitle>
                                    <CardDescription>Configure webhooks for processing completion notifications</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <h3 class="text-lg font-medium mb-4">Webhook Configuration</h3>
                                        <div class="space-y-4">
                                            <div>
                                                <Label for="webhookUrl">Webhook URL</Label>
                                                <Input 
                                                    id="webhookUrl"
                                                    v-model="settings.api.webhookUrl" 
                                                    type="url"
                                                    placeholder="https://your-app.com/webhook"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">URL to receive processing completion notifications</p>
                                            </div>
                                            <div>
                                                <Label for="webhookSecret">Webhook Secret</Label>
                                                <Input 
                                                    id="webhookSecret"
                                                    v-model="settings.api.webhookSecret" 
                                                    type="password"
                                                    placeholder="Secret key for webhook verification"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">Secret key used to verify webhook authenticity</p>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <Checkbox 
                                                    id="enableWebhooks"
                                                    v-model:checked="settings.api.enableWebhooks"
                                                />
                                                <Label for="enableWebhooks">Enable webhooks</Label>
                                            </div>
                                            <Button v-if="settings.api.enableWebhooks" variant="outline" size="sm">
                                                <Zap class="h-4 w-4 mr-2" />
                                                Test Webhook
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Advanced Settings -->
                        <div v-if="activeTab === 'advanced'" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Processing Configuration</CardTitle>
                                    <CardDescription>Configure how payslips are processed</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <Label for="processingMode">Processing Mode</Label>
                                        <select 
                                            id="processingMode"
                                            v-model="settings.advanced.processingMode" 
                                            class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm"
                                        >
                                            <option value="sync">Synchronous (Immediate)</option>
                                            <option value="async">Asynchronous (Queue)</option>
                                        </select>
                                        <p class="text-sm text-muted-foreground mt-1">
                                            <strong>Sync:</strong> Process payslips immediately (no queue:work needed)<br>
                                            <strong>Async:</strong> Use queue system (requires queue:work to be running)
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Performance Tuning</CardTitle>
                                    <CardDescription>Advanced performance and optimization settings</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div>
                                        <h3 class="text-lg font-medium mb-4">Performance Tuning</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label for="cacheDuration">Cache Duration (minutes)</Label>
                                                <Input 
                                                    id="cacheDuration"
                                                    v-model="settings.advanced.cacheDuration" 
                                                    type="number" 
                                                    min="5" 
                                                    max="1440"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">How long to cache settings and other data</p>
                                            </div>
                                            <div>
                                                <Label for="queueTimeout">Queue Timeout (seconds)</Label>
                                                <Input 
                                                    id="queueTimeout"
                                                    v-model="settings.advanced.queueTimeout" 
                                                    type="number" 
                                                    min="60" 
                                                    max="3600"
                                                    class="mt-1"
                                                />
                                                <p class="text-sm text-muted-foreground mt-1">Maximum time for processing jobs before timeout</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <Label for="memoryLimit">Memory Limit (MB)</Label>
                                        <div class="flex items-center space-x-4 mt-2">
                                            <input 
                                                type="range" 
                                                id="memoryLimit"
                                                v-model="settings.advanced.memoryLimit" 
                                                min="128" 
                                                max="2048" 
                                                step="64"
                                                class="flex-1"
                                            />
                                            <span class="text-sm font-medium min-w-[60px]">{{ settings.advanced.memoryLimit }}MB</span>
                                        </div>
                                        <p class="text-sm text-muted-foreground mt-1">Memory limit for processing jobs</p>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="enableDebugMode"
                                                v-model:checked="settings.advanced.enableDebugMode"
                                            />
                                            <Label for="enableDebugMode">Enable debug mode</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="enableProfiling"
                                                v-model:checked="settings.advanced.enableProfiling"
                                            />
                                            <Label for="enableProfiling">Enable performance profiling</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox 
                                                id="compressImages"
                                                v-model:checked="settings.advanced.compressImages"
                                            />
                                            <Label for="compressImages">Compress uploaded images</Label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Queue Settings</CardTitle>
                                    <CardDescription>Configure queue worker behavior</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label for="queueWorkerSleep">Worker Sleep (seconds)</Label>
                                            <Input 
                                                id="queueWorkerSleep"
                                                v-model="settings.advanced.queueWorkerSleep" 
                                                type="number" 
                                                min="1" 
                                                max="60"
                                                class="mt-1"
                                            />
                                            <p class="text-sm text-muted-foreground mt-1">Sleep time between queue checks</p>
                                        </div>
                                        <div>
                                            <Label for="queueWorkerTries">Max Tries</Label>
                                            <Input 
                                                id="queueWorkerTries"
                                                v-model="settings.advanced.queueWorkerTries" 
                                                type="number" 
                                                min="1" 
                                                max="10"
                                                class="mt-1"
                                            />
                                            <p class="text-sm text-muted-foreground mt-1">Maximum retry attempts for failed jobs</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Batch Processing</CardTitle>
                                    <CardDescription>Configure batch processing settings</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label for="batchProcessingTimeout">Batch Timeout (seconds)</Label>
                                            <Input 
                                                id="batchProcessingTimeout"
                                                v-model="settings.advanced.batchProcessingTimeout" 
                                                type="number" 
                                                min="300" 
                                                max="7200"
                                                class="mt-1"
                                            />
                                            <p class="text-sm text-muted-foreground mt-1">Timeout for batch processing jobs</p>
                                        </div>
                                        <div>
                                            <Label for="batchProcessingBackoff">Batch Backoff (seconds)</Label>
                                            <Input 
                                                id="batchProcessingBackoff"
                                                v-model="settings.advanced.batchProcessingBackoff" 
                                                type="number" 
                                                min="10" 
                                                max="300"
                                                class="mt-1"
                                            />
                                            <p class="text-sm text-muted-foreground mt-1">Base backoff time for retry attempts</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Maintenance</CardTitle>
                                    <CardDescription>System maintenance and cleanup options</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <Button variant="outline" size="sm">
                                            <Trash2 class="h-4 w-4 mr-2" />
                                            Clear Cache
                                        </Button>
                                        <Button variant="outline" size="sm">
                                            <FileText class="h-4 w-4 mr-2" />
                                            Clear Logs
                                        </Button>
                                        <Button variant="outline" size="sm">
                                            <Database class="h-4 w-4 mr-2" />
                                            Optimize Database
                                        </Button>
                                        <Button variant="outline" size="sm">
                                            <Activity class="h-4 w-4 mr-2" />
                                            Run Health Check
                                        </Button>
                                    </div>

                                    <div class="border-t pt-6">
                                        <h3 class="text-lg font-medium mb-4 text-red-600 dark:text-red-400">Danger Zone</h3>
                                        <p class="text-sm text-muted-foreground mb-4">These actions cannot be undone.</p>
                                        <Button variant="destructive" size="sm">
                                            <AlertTriangle class="h-4 w-4 mr-2" />
                                            Reset System
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reset Confirmation Dialog -->
            <Dialog v-model:open="showResetConfirmation">
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center text-destructive">
                            <AlertTriangle class="h-5 w-5 mr-2" />
                            Reset System
                        </DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            This will reset all settings to their default values and clear all uploaded files. This action cannot be undone.
                        </p>
                        <div class="flex justify-end space-x-2">
                            <Button variant="outline" @click="showResetConfirmation = false">Cancel</Button>
                            <Button variant="destructive" @click="confirmReset">Reset System</Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </template>

        <!-- Access Denied Message -->
        <template v-else>
            <div class="flex flex-col h-full items-center justify-center p-8">
            <Card class="w-full max-w-md">
                <CardContent class="p-8 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <div class="p-4 bg-muted rounded-full">
                            <Shield class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Access Restricted</h3>
                            <p class="text-muted-foreground">You don't have permission to manage system settings.</p>
                            <p class="text-sm text-muted-foreground mt-2">Contact your administrator for access.</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </template>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { 
    Settings, Eye, Cpu, Database, Activity, Save, RotateCcw, Plus, Trash2, 
    TestTube, FileText, AlertTriangle, Shield, Zap
} from 'lucide-vue-next';
import { usePermissions } from '@/composables/usePermissions';
import { Head } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';

const activeTab = ref('general');
const isSaving = ref(false);
const isResetting = ref(false);
const isClearing = ref(false);
const isOptimizing = ref(false);
const isChecking = ref(false);
const showResetConfirmation = ref(false);

const tabs = [
    { id: 'general', name: 'General', icon: Settings },
    { id: 'ocr', name: 'OCR', icon: Eye },
    { id: 'api', name: 'API', icon: Database },
    { id: 'advanced', name: 'Advanced', icon: Cpu },
];

const ocrLanguages = [
    { code: 'eng', name: 'English' },
    { code: 'msa', name: 'Malay' },
    { code: 'chi_sim', name: 'Chinese (Simplified)' },
    { code: 'chi_tra', name: 'Chinese (Traditional)' },
];

const systemHealth = ref({
    queueCount: 0,
    storageUsed: 0,
});

const settings = ref({
    general: {
        systemName: 'Payslip AI',
        defaultLanguage: 'en',
        enableNotifications: true,
        autoCleanup: true,
        maxFileSize: 10,
        concurrentProcessing: 3,
        allowedFileTypes: ['pdf', 'png', 'jpg', 'jpeg'],
    },
    ocr: {
        engine: 'tesseract',
        dpi: '300',
        languages: ['eng', 'msa'],
        preprocessing: true,
        autoRotate: true,
        enhanceContrast: false,
        confidenceThreshold: 70,
        customPatterns: [
            { name: 'Gaji Bersih', regex: 'Gaji\\s+Bersih.*?([\\d,]+\\.\\d{2})' },
            { name: 'Peratus Gaji Bersih', regex: '%\\s*Peratus\\s+Gaji\\s+Bersih.*?([\\d.]+)' },
        ],
    },
    api: {
        rateLimitPerMinute: 60,
        rateLimitPerHour: 1000,
        enableRateLimit: true,
        enableCors: true,
        requireAuth: true,
        logRequests: true,
        webhookUrl: '',
        webhookSecret: '',
        enableWebhooks: false,
    },
    advanced: {
        processingMode: 'sync',
        cacheDuration: 60,
        queueTimeout: 300,
        memoryLimit: 512,
        enableDebugMode: false,
        enableProfiling: false,
        compressImages: true,
        queueWorkerSleep: 3,
        queueWorkerTries: 3,
        batchProcessingTimeout: 3600,
        batchProcessingBackoff: 60,
    },
});

const { canManageSettings, canViewSystemHealth } = usePermissions();

// Available file types that can be enabled/disabled
const availableFileTypes = ['pdf', 'png', 'jpg', 'jpeg'];

onMounted(() => {
    console.log('Settings page mounted');
    console.log('canManageSettings:', canManageSettings.value);
    console.log('canViewSystemHealth:', canViewSystemHealth.value);
    
    // Temporarily always fetch settings for testing
    fetchSettings();
    
    if (canViewSystemHealth.value) {
        fetchSystemHealth();
    }
});

const fetchSystemHealth = async () => {
    try {
        const response = await fetch('/api/system/health', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        });
        if (response.ok) {
            const data = await response.json();
            systemHealth.value = data;
        }
    } catch (e) {
        console.error('Failed to fetch system health:', e);
    }
};

const fetchSettings = async () => {
    try {
        const response = await fetch('/api/settings/', {
            method: 'GET',
            credentials: 'same-origin', // Include cookies for session auth
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest', // Laravel expects this for AJAX
            }
        });
        
        console.log('Settings API Response Status:', response.status);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Settings API Response Data:', data);
            
            if (data.success && data.data && data.data.settings) {
                const apiSettings = data.data.settings;
                console.log('API Settings Structure:', apiSettings);
                
                // Helper function to safely get setting value
                const getSetting = (category: string, key: string, defaultValue: any) => {
                    const value = apiSettings[category]?.[key];
                    console.log(`Getting ${category}.${key}:`, value, 'default:', defaultValue);
                    
                    // Handle JSON type settings that should be arrays
                    if (key === 'general.allowed_file_types' || key === 'ocr.languages') {
                        // If it's already an array, use it as is
                        if (Array.isArray(value)) {
                            return value;
                        }
                        // If it's a string, try to parse it
                        if (typeof value === 'string') {
                            try {
                                return JSON.parse(value);
                            } catch (e) {
                                console.warn('Failed to parse JSON setting:', key, value);
                                return defaultValue;
                            }
                        }
                    }
                    
                    return value !== undefined && value !== null ? value : defaultValue;
                };
                
                // Map API settings to component structure - fix boolean handling
                settings.value.general = {
                    systemName: getSetting('general', 'general.system_name', 'Payslip AI'),
                    defaultLanguage: getSetting('general', 'general.default_language', 'en'),
                    enableNotifications: getSetting('general', 'general.enable_notifications', true),
                    autoCleanup: getSetting('general', 'general.auto_cleanup', true),
                    maxFileSize: getSetting('general', 'general.max_file_size', 10),
                    concurrentProcessing: getSetting('general', 'general.concurrent_processing', 3),
                    allowedFileTypes: getSetting('general', 'general.allowed_file_types', ['pdf', 'png', 'jpg', 'jpeg']),
                };
                
                settings.value.ocr = {
                    engine: getSetting('ocr', 'ocr.engine', 'tesseract'),
                    dpi: getSetting('ocr', 'ocr.dpi', '300'),
                    languages: getSetting('ocr', 'ocr.languages', ['eng', 'msa']),
                    preprocessing: getSetting('ocr', 'ocr.preprocessing', true),
                    autoRotate: getSetting('ocr', 'ocr.auto_rotate', true),
                    enhanceContrast: getSetting('ocr', 'ocr.enhance_contrast', false),
                    confidenceThreshold: getSetting('ocr', 'ocr.confidence_threshold', 70),
                    customPatterns: [
                        { name: 'Gaji Bersih', regex: 'Gaji\\s+Bersih.*?([\\d,]+\\.\\d{2})' },
                        { name: 'Peratus Gaji Bersih', regex: '%\\s*Peratus\\s+Gaji\\s+Bersih.*?([\\d.]+)' },
                    ],
                };
                
                settings.value.api = {
                    rateLimitPerMinute: getSetting('api', 'api.rate_limit_per_minute', 60),
                    rateLimitPerHour: getSetting('api', 'api.rate_limit_per_hour', 1000),
                    enableRateLimit: getSetting('api', 'api.enable_rate_limit', true),
                    enableCors: getSetting('api', 'api.enable_cors', true),
                    requireAuth: getSetting('api', 'api.require_auth', true),
                    logRequests: getSetting('api', 'api.log_requests', true),
                    webhookUrl: '',
                    webhookSecret: '',
                    enableWebhooks: false,
                };
                
                settings.value.advanced = {
                    processingMode: getSetting('advanced', 'advanced.processing_mode', 'sync'),
                    cacheDuration: getSetting('advanced', 'advanced.cache_duration', 60),
                    queueTimeout: getSetting('advanced', 'advanced.queue_timeout', 300),
                    memoryLimit: getSetting('advanced', 'advanced.memory_limit', 512),
                    enableDebugMode: getSetting('advanced', 'advanced.enable_debug_mode', false),
                    enableProfiling: getSetting('advanced', 'advanced.enable_profiling', false),
                    compressImages: getSetting('advanced', 'advanced.compress_images', true),
                    queueWorkerSleep: getSetting('advanced', 'advanced.queue_worker_sleep', 3),
                    queueWorkerTries: getSetting('advanced', 'advanced.queue_worker_tries', 3),
                    batchProcessingTimeout: getSetting('advanced', 'advanced.batch_processing_timeout', 3600),
                    batchProcessingBackoff: getSetting('advanced', 'advanced.batch_processing_backoff', 60),
                };
                
                console.log('Final settings state:', settings.value);
                console.log('Allowed file types specifically:', settings.value.general.allowedFileTypes);
                console.log('Type of allowedFileTypes:', typeof settings.value.general.allowedFileTypes);
                console.log('Is array:', Array.isArray(settings.value.general.allowedFileTypes));
                
                // Force Vue reactivity update
                await nextTick();
            } else {
                console.error('Invalid API response structure:', data);
            }
        } else {
            const errorText = await response.text();
            console.error('Settings API failed:', response.status, errorText);
        }
    } catch (e) {
        console.error('Failed to fetch settings:', e);
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    try {
        // Prepare settings for API
        const apiSettings = {
            'general.system_name': settings.value.general.systemName,
            'general.default_language': settings.value.general.defaultLanguage,
            'general.enable_notifications': settings.value.general.enableNotifications,
            'general.auto_cleanup': settings.value.general.autoCleanup,
            'general.max_file_size': settings.value.general.maxFileSize,
            'general.concurrent_processing': settings.value.general.concurrentProcessing,
            'general.allowed_file_types': JSON.stringify(settings.value.general.allowedFileTypes),
            'ocr.engine': settings.value.ocr.engine,
            'ocr.dpi': settings.value.ocr.dpi,
            'ocr.languages': settings.value.ocr.languages,
            'ocr.preprocessing': settings.value.ocr.preprocessing,
            'ocr.auto_rotate': settings.value.ocr.autoRotate,
            'ocr.enhance_contrast': settings.value.ocr.enhanceContrast,
            'ocr.confidence_threshold': settings.value.ocr.confidenceThreshold,
            'api.rate_limit_per_minute': settings.value.api.rateLimitPerMinute,
            'api.rate_limit_per_hour': settings.value.api.rateLimitPerHour,
            'api.enable_rate_limit': settings.value.api.enableRateLimit,
            'api.enable_cors': settings.value.api.enableCors,
            'api.require_auth': settings.value.api.requireAuth,
            'api.log_requests': settings.value.api.logRequests,
            'advanced.processing_mode': settings.value.advanced.processingMode,
            'advanced.cache_duration': settings.value.advanced.cacheDuration,
            'advanced.queue_timeout': settings.value.advanced.queueTimeout,
            'advanced.queue_worker_sleep': settings.value.advanced.queueWorkerSleep,
            'advanced.queue_worker_tries': settings.value.advanced.queueWorkerTries,
            'advanced.batch_processing_timeout': settings.value.advanced.batchProcessingTimeout,
            'advanced.batch_processing_backoff': settings.value.advanced.batchProcessingBackoff,
            'advanced.memory_limit': settings.value.advanced.memoryLimit,
            'advanced.enable_debug_mode': settings.value.advanced.enableDebugMode,
            'advanced.enable_profiling': settings.value.advanced.enableProfiling,
            'advanced.compress_images': settings.value.advanced.compressImages,
        };

        const response = await fetch('/api/settings/', {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                settings: apiSettings
            })
        });

        const data = await response.json();
        
        if (data.success) {
            // Show success message
            console.log('Settings saved successfully');
        } else {
            throw new Error(data.message || 'Failed to save settings');
        }
    } catch (e) {
        console.error('Failed to save settings:', e);
        // Show error message
    }
    isSaving.value = false;
};

const resetToDefaults = async () => {
    isResetting.value = true;
    try {
        const response = await fetch('/api/settings/reset-all', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        });

        const data = await response.json();
        
        if (data.success) {
            // Reload settings from API
            await fetchSettings();
            console.log('Settings reset to defaults');
        } else {
            throw new Error(data.message || 'Failed to reset settings');
        }
    } catch (e) {
        console.error('Failed to reset settings:', e);
    }
    isResetting.value = false;
};

const toggleFileType = (type: string, checked: boolean) => {
    console.log(`toggleFileType called: type=${type}, checked=${checked}`);
    console.log('Current allowedFileTypes before change:', settings.value.general.allowedFileTypes);
    
    // Create a new array to ensure Vue detects the change
    const currentTypes = [...settings.value.general.allowedFileTypes];
    
    if (checked && !currentTypes.includes(type)) {
        currentTypes.push(type);
    } else if (!checked && currentTypes.includes(type)) {
        const index = currentTypes.indexOf(type);
        currentTypes.splice(index, 1);
    }
    
    // Assign the new array to trigger reactivity
    settings.value.general.allowedFileTypes = currentTypes;
    console.log('Updated allowed file types:', settings.value.general.allowedFileTypes);
};

const toggleLanguage = (langCode: string, checked: boolean) => {
    if (checked && !settings.value.ocr.languages.includes(langCode)) {
        settings.value.ocr.languages.push(langCode);
    } else if (!checked && settings.value.ocr.languages.includes(langCode)) {
        const index = settings.value.ocr.languages.indexOf(langCode);
        settings.value.ocr.languages.splice(index, 1);
    }
};

const addPattern = () => {
    settings.value.ocr.customPatterns.push({ name: '', regex: '' });
};

const removePattern = (index: number) => {
    settings.value.ocr.customPatterns.splice(index, 1);
};

const testWebhook = async () => {
    try {
        
        // Implement webhook test
    } catch (e) {
        
    }
};

const clearCache = async () => {
    isClearing.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 2000));
        
    } catch (e) {
        
    }
    isClearing.value = false;
};

const clearLogs = async () => {
    isClearing.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 2000));
        
    } catch (e) {
        
    }
    isClearing.value = false;
};

const optimizeDatabase = async () => {
    isOptimizing.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 3000));
        
    } catch (e) {
        
    }
    isOptimizing.value = false;
};

const runHealthCheck = async () => {
    isChecking.value = true;
    try {
        await fetchSystemHealth();
        
    } catch (e) {
        
    }
    isChecking.value = false;
};

const confirmReset = async () => {
    try {
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        showResetConfirmation.value = false;
        location.reload();
    } catch (e) {
        
    }
};

onMounted(() => {
    fetchSystemHealth();
});
</script> 