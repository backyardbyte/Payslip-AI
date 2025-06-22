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
                                            <div v-for="type in ['pdf', 'png', 'jpg', 'jpeg']" :key="type" class="flex items-center space-x-2">
                                                <Checkbox :checked="settings.general.allowedFileTypes.includes(type)" @update:checked="toggleFileType(type)" />
                                                <Label class="text-sm">{{ type.toUpperCase() }}</Label>
                                            </div>
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
                                                <Checkbox :checked="settings.ocr.languages.includes(lang.code)" @update:checked="toggleLanguage(lang.code)" />
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
                                        <Label>Rate Limiting</Label>
                                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label class="text-sm text-muted-foreground">Requests per minute</Label>
                                                <input
                                                    v-model.number="settings.api.rateLimitPerMinute"
                                                    type="number"
                                                    min="10"
                                                    max="1000"
                                                    class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                />
                                            </div>
                                            <div>
                                                <Label class="text-sm text-muted-foreground">Requests per hour</Label>
                                                <input
                                                    v-model.number="settings.api.rateLimitPerHour"
                                                    type="number"
                                                    min="100"
                                                    max="10000"
                                                    class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.api.enableCors" />
                                            <Label>Enable CORS</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.api.requireAuth" />
                                            <Label>Require authentication for API access</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.api.logRequests" />
                                            <Label>Log API requests</Label>
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
                                        <Label>Webhook URL</Label>
                                        <input
                                            v-model="settings.api.webhookUrl"
                                            type="url"
                                            placeholder="https://your-app.com/webhook"
                                            class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        />
                                    </div>
                                    <div>
                                        <Label>Webhook Secret</Label>
                                        <input
                                            v-model="settings.api.webhookSecret"
                                            type="password"
                                            placeholder="Secret key for webhook verification"
                                            class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                        />
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.api.enableWebhooks" />
                                            <Label>Enable webhooks</Label>
                                        </div>
                                        <Button variant="outline" size="sm" @click="testWebhook">
                                            <TestTube class="h-4 w-4 mr-2" />
                                            Test Webhook
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Advanced Settings -->
                        <div v-if="activeTab === 'advanced'" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Performance Tuning</CardTitle>
                                    <CardDescription>Advanced performance and optimization settings</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label>Cache Duration (minutes)</Label>
                                            <input
                                                v-model.number="settings.advanced.cacheDuration"
                                                type="number"
                                                min="5"
                                                max="1440"
                                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                        <div>
                                            <Label>Queue Timeout (seconds)</Label>
                                            <input
                                                v-model.number="settings.advanced.queueTimeout"
                                                type="number"
                                                min="30"
                                                max="3600"
                                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label>Memory Limit (MB)</Label>
                                        <div class="mt-2 flex items-center space-x-4">
                                            <input
                                                v-model.number="settings.advanced.memoryLimit"
                                                type="range"
                                                min="128"
                                                max="2048"
                                                step="128"
                                                class="flex-1"
                                            />
                                            <span class="text-sm font-mono w-16">{{ settings.advanced.memoryLimit }}MB</span>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.advanced.enableDebugMode" />
                                            <Label>Enable debug mode</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.advanced.enableProfiling" />
                                            <Label>Enable performance profiling</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox v-model:checked="settings.advanced.compressImages" />
                                            <Label>Compress uploaded images</Label>
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
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <Button variant="outline" @click="clearCache" :disabled="isClearing">
                                            <Trash2 :class="['h-4 w-4 mr-2', isClearing && 'animate-pulse']" />
                                            {{ isClearing ? 'Clearing...' : 'Clear Cache' }}
                                        </Button>
                                        <Button variant="outline" @click="clearLogs" :disabled="isClearing">
                                            <FileText :class="['h-4 w-4 mr-2', isClearing && 'animate-pulse']" />
                                            {{ isClearing ? 'Clearing...' : 'Clear Logs' }}
                                        </Button>
                                        <Button variant="outline" @click="optimizeDatabase" :disabled="isOptimizing">
                                            <Database :class="['h-4 w-4 mr-2', isOptimizing && 'animate-spin']" />
                                            {{ isOptimizing ? 'Optimizing...' : 'Optimize Database' }}
                                        </Button>
                                        <Button variant="outline" @click="runHealthCheck" :disabled="isChecking">
                                            <Activity :class="['h-4 w-4 mr-2', isChecking && 'animate-pulse']" />
                                            {{ isChecking ? 'Checking...' : 'Run Health Check' }}
                                        </Button>
                                    </div>

                                    <div class="pt-4 border-t">
                                        <h4 class="font-medium text-destructive mb-2">Danger Zone</h4>
                                        <p class="text-sm text-muted-foreground mb-4">These actions cannot be undone.</p>
                                        <div class="flex gap-2">
                                            <Button variant="destructive" size="sm" @click="showResetConfirmation = true">
                                                <AlertTriangle class="h-4 w-4 mr-2" />
                                                Reset System
                                            </Button>
                                        </div>
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
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { 
    Settings, Eye, Cpu, Database, Activity, Save, RotateCcw, Plus, Trash2, 
    TestTube, FileText, AlertTriangle, Shield
} from 'lucide-vue-next';
import { usePermissions } from '@/composables/usePermissions';
import { Head } from '@inertiajs/vue3';

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
        enableCors: true,
        requireAuth: true,
        logRequests: true,
        webhookUrl: '',
        webhookSecret: '',
        enableWebhooks: false,
    },
    advanced: {
        cacheDuration: 60,
        queueTimeout: 300,
        memoryLimit: 512,
        enableDebugMode: false,
        enableProfiling: false,
        compressImages: true,
    },
});

const { canManageSettings, canViewSystemHealth } = usePermissions();

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
        // Handle health check error
    }
};

const saveSettings = async () => {
    isSaving.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call
        // Settings saved successfully
        // Show success notification
    } catch (e) {
        
    }
    isSaving.value = false;
};

const resetToDefaults = async () => {
    isResetting.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate reset
        // Reset to default values
        location.reload(); // Simple reset for demo
    } catch (e) {
        
    }
    isResetting.value = false;
};

const toggleFileType = (type: string) => {
    const index = settings.value.general.allowedFileTypes.indexOf(type);
    if (index > -1) {
        settings.value.general.allowedFileTypes.splice(index, 1);
    } else {
        settings.value.general.allowedFileTypes.push(type);
    }
};

const toggleLanguage = (langCode: string) => {
    const index = settings.value.ocr.languages.indexOf(langCode);
    if (index > -1) {
        settings.value.ocr.languages.splice(index, 1);
    } else {
        settings.value.ocr.languages.push(langCode);
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