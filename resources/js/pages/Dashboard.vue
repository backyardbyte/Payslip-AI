<template>
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-900/50">
                                <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Total</p>
                                <p class="text-2xl font-bold">{{ statistics?.stats?.total || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-yellow-100 rounded-lg dark:bg-yellow-900/50">
                                <Clock class="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Queued</p>
                                <p class="text-2xl font-bold">{{ statistics?.stats?.queued || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-orange-100 rounded-lg dark:bg-orange-900/50">
                                <LoaderCircle class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Processing</p>
                                <p class="text-2xl font-bold">{{ statistics?.stats?.processing || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-green-100 rounded-lg dark:bg-green-900/50">
                                <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Completed</p>
                                <p class="text-2xl font-bold">{{ statistics?.stats?.completed || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <div class="p-2 bg-red-100 rounded-lg dark:bg-red-900/50">
                                <XCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">Failed</p>
                                <p class="text-2xl font-bold">{{ statistics?.stats?.failed || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Upload Section -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <UploadCloud class="h-5 w-5" />
                        Payslip Uploader
                    </CardTitle>
                    <CardDescription>Drag and drop your payslips below or click to select files.</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-6">
                    <div
                        @dragover.prevent="onDragOver"
                        @dragleave.prevent="onDragLeave"
                        @drop.prevent="onDrop"
                        :class="['flex flex-col items-center justify-center p-12 border-2 border-dashed rounded-lg cursor-pointer transition-colors', isDragging ? 'border-primary bg-primary/10' : 'border-border']"
                        @click="openFileDialog"
                    >
                        <input type="file" ref="fileInput" @change="onFileSelected" multiple class="hidden" accept=".pdf,.png,.jpg,.jpeg" />
                        <UploadCloud class="w-12 h-12 text-muted-foreground" />
                        <p class="mt-4 text-muted-foreground">Click or drag files here to upload</p>
                        <p class="mt-2 text-xs text-muted-foreground">Supports PDF, PNG, JPG, JPEG (max 5MB)</p>
                    </div>

                    <div v-if="files.length > 0" class="space-y-4">
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold">Files to Upload</h3>
                             <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                <div v-for="file in files" :key="file.id" class="relative group border rounded-lg overflow-hidden text-sm">
                                    <div class="relative w-full h-32">
                                        <img v-if="file.previewUrl" :src="file.previewUrl" class="absolute inset-0 object-cover w-full h-full" alt="File preview" />
                                        <div v-else class="w-full h-full flex items-center justify-center bg-muted">
                                            <FileText class="w-10 h-10 text-muted-foreground" />
                                        </div>
                                        <div v-if="file.status === 'uploading'" class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center gap-2 text-white">
                                            <LoaderCircle class="w-6 h-6 animate-spin" />
                                            <span class="font-medium">{{ file.progress.toFixed(0) }}%</span>
                                        </div>
                                        <div v-if="file.status === 'error'" class="absolute inset-0 bg-red-900/80 flex flex-col items-center justify-center gap-2 text-white p-2 text-center">
                                            <XCircle class="w-6 h-6" />
                                            <span class="font-semibold">Upload Failed</span>
                                            <span v-if="file.error" class="text-xs px-1">{{ file.error }}</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-1 right-1 z-10 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                         <Button variant="secondary" size="icon" class="h-7 w-7" @click.stop="previewFile(file)">
                                            <Eye class="w-4 h-4" />
                                        </Button>
                                        <Button variant="destructive" size="icon" class="h-7 w-7" @click.stop="removeFile(file.id)">
                                            <Trash2 class="w-4 h-4" />
                                        </Button>
                                    </div>
                                    <div class="p-2 border-t bg-card">
                                        <p class="font-medium truncate">{{ file.file.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ (file.file.size / 1024).toFixed(2) }} KB</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Button @click="uploadPayslips" class="w-full" :disabled="isUploading || getPendingFiles().length === 0">
                            <LoaderCircle v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
                            {{ isUploading ? 'Uploading...' : `Upload ${getPendingFiles().length} File(s)` }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Processing Queue -->
            <Card v-if="queuedFiles.length > 0">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <List class="h-5 w-5" />
                                Processing Queue
                            </CardTitle>
                            <CardDescription>These files have been uploaded and are awaiting processing.</CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" @click="refreshQueue" :disabled="isRefreshing">
                                <RefreshCw :class="['h-4 w-4', isRefreshing && 'animate-spin']" />
                                Refresh
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Settings class="h-4 w-4 mr-2" />
                                        Manage
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="clearCompleted" :disabled="isClearing">
                                        <CheckCircle class="h-4 w-4 mr-2" />
                                        Clear Recent Items
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="clearAll" :disabled="isClearing" class="text-red-600">
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Clear Queue
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <Collapsible v-for="file in queuedFiles" :key="file.job_id" class="text-sm border rounded-lg">
                             <div class="flex items-center justify-between p-3">
                                <div class="flex items-center gap-3">
                                    <FileText class="w-6 h-6 text-muted-foreground" />
                                    <div class="flex flex-col">
                                        <p class="font-medium truncate">{{ file.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ (file.size / 1024).toFixed(2) }} KB
                                            <span v-if="file.created_at"> - {{ new Date(file.created_at).toLocaleString() }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span :class="['px-2 py-0.5 text-xs font-semibold rounded-full', statusClass(file.status)]">
                                        {{ file.status.charAt(0).toUpperCase() + file.status.slice(1) }}
                                    </span>
                                    <div class="flex gap-2">
                                        <CollapsibleTrigger asChild>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <ChevronDown class="h-4 w-4" />
                                            </Button>
                                        </CollapsibleTrigger>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 hover:bg-red-100 hover:text-red-600" @click="deletePayslip(file.id)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <CollapsibleContent>
                                <div class="p-4 border-t bg-muted/50">
                                    <div v-if="file.status === 'failed' && file.data?.error" class="p-3 text-red-700 bg-red-100 border border-red-200 rounded-md">
                                        <p class="font-bold">Processing Error:</p>
                                        <p class="mt-1 font-mono text-xs">{{ file.data.error }}</p>
                                    </div>
                                    <div v-else-if="file.status === 'completed' && file.data" class="space-y-3">
                                        <div>
                                            <h4 class="font-semibold">Extracted Data</h4>
                                            <div class="p-3 mt-1 space-y-2 text-sm border rounded-md bg-background">
                                                <div v-if="file.data.nama" class="flex justify-between">
                                                    <span class="text-muted-foreground">Name:</span>
                                                    <span class="font-medium">{{ file.data.nama }}</span>
                                                </div>
                                                <div v-if="file.data.no_gaji" class="flex justify-between">
                                                    <span class="text-muted-foreground">No. Gaji:</span>
                                                    <span class="font-mono">{{ file.data.no_gaji }}</span>
                                                </div>
                                                <div v-if="file.data.bulan" class="flex justify-between">
                                                    <span class="text-muted-foreground">Month:</span>
                                                    <span class="font-mono">{{ file.data.bulan }}</span>
                                                </div>
                                                <div v-if="file.data.gaji_pokok" class="flex justify-between">
                                                    <span class="text-muted-foreground">Gaji Pokok:</span>
                                                    <span class="font-mono">RM {{ file.data.gaji_pokok?.toLocaleString() }}</span>
                                                </div>
                                                <div v-if="file.data.jumlah_pendapatan" class="flex justify-between">
                                                    <span class="text-muted-foreground">Jumlah Pendapatan:</span>
                                                    <span class="font-mono">RM {{ file.data.jumlah_pendapatan?.toLocaleString() }}</span>
                                                </div>
                                                <div v-if="file.data.jumlah_potongan" class="flex justify-between">
                                                    <span class="text-muted-foreground">Jumlah Potongan:</span>
                                                    <span class="font-mono">RM {{ file.data.jumlah_potongan?.toLocaleString() }}</span>
                                                </div>
                                                <div v-if="file.data.gaji_bersih" class="flex justify-between">
                                                    <span class="text-muted-foreground">Gaji Bersih:</span>
                                                    <span class="font-mono font-semibold">RM {{ file.data.gaji_bersih?.toLocaleString() }}</span>
                                                </div>
                                                <div class="flex justify-between pt-2 border-t">
                                                    <span class="text-muted-foreground">% Peratus Gaji Bersih:</span>
                                                    <span class="font-bold text-primary">{{ file.data.peratus_gaji_bersih ?? 'N/A' }}%</span>
                                                </div>
                                                <div v-if="file.data.debug_info" class="pt-2 border-t">
                                                    <details class="text-xs">
                                                        <summary class="cursor-pointer text-muted-foreground hover:text-foreground">Debug Info</summary>
                                                        <div class="mt-2 p-2 bg-muted rounded text-xs font-mono">
                                                            <p>Text Length: {{ file.data.debug_info.text_length }}</p>
                                                            <p>Patterns Found: {{ file.data.debug_info.extraction_patterns_found?.join(', ') || 'None' }}</p>
                                                        </div>
                                                    </details>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold">Koperasi Eligibility</h4>
                                            <div class="p-3 mt-1 space-y-2 border rounded-md bg-background">
                                                <div v-if="!file.data.koperasi_results || Object.keys(file.data.koperasi_results).length === 0">
                                                    <p class="text-sm text-muted-foreground">No active koperasi found to check against.</p>
                                                </div>
                                                <div v-else v-for="(isEligible, name) in file.data.koperasi_results" :key="name" class="flex items-center justify-between">
                                                    <span class="font-medium">{{ name }}</span>
                                                    <span :class="['px-2 py-0.5 text-xs font-semibold rounded-full', isEligible ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                                        {{ isEligible ? 'Eligible' : 'Not Eligible' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </CardContent>
            </Card>

            <!-- File Preview Dialog -->
            <Dialog v-model:open="isPreviewOpen">
                <DialogContent class="max-w-4xl max-h-[80vh]">
                    <DialogHeader>
                        <DialogTitle>File Preview</DialogTitle>
                    </DialogHeader>
                    <div v-if="fileToPreview" class="flex items-center justify-center p-4 bg-muted rounded-lg">
                        <img v-if="fileToPreview.previewUrl" :src="fileToPreview.previewUrl" class="max-w-full max-h-96 object-contain" alt="File preview" />
                        <div v-else class="flex flex-col items-center gap-4 text-muted-foreground">
                            <FileText class="w-16 h-16" />
                            <p>{{ fileToPreview.file.name }}</p>
                            <p class="text-sm">{{ (fileToPreview.file.size / 1024).toFixed(2) }} KB</p>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { 
    UploadCloud, FileText, LoaderCircle, XCircle, Eye, Trash2, ChevronDown, 
    Clock, CheckCircle, List, RefreshCw, Settings
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';

interface UploadableFile {
    id: string;
    file: File;
    progress: number;
    status: 'pending' | 'uploading' | 'success' | 'error';
    job_id?: string;
    error?: string;
    previewUrl?: string;
}

interface QueuedFile {
    id: number;
    name: string;
    size: number;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    job_id: string;
    created_at?: string;
    data?: {
        nama?: string;
        no_gaji?: string;
        bulan?: string;
        gaji_pokok?: number;
        jumlah_pendapatan?: number;
        jumlah_potongan?: number;
        gaji_bersih?: number;
        peratus_gaji_bersih?: number;
        koperasi_results?: Record<string, boolean>;
        error?: string;
        debug_info?: {
            text_length: number;
            extraction_patterns_found: string[];
        };
    };
}

interface Statistics {
    stats: {
        total: number;
        queued: number;
        processing: number;
        completed: number;
        failed: number;
    };
    recent_activity: Array<{
        id: number;
        name: string;
        status: string;
        created_at: string;
    }>;
}

const files = ref<UploadableFile[]>([]);
const queuedFiles = ref<QueuedFile[]>([]);
const statistics = ref<Statistics | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const isRefreshing = ref(false);
const isClearing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const isPreviewOpen = ref(false);
const fileToPreview = ref<UploadableFile | null>(null);
let pollingInterval: number | undefined;

const onDragOver = () => { isDragging.value = true; };
const onDragLeave = () => { isDragging.value = false; };

const addFiles = (fileList: FileList | null) => {
    if (!fileList) return;
    const newFiles: UploadableFile[] = Array.from(fileList).map(file => ({
        id: `file_${Math.random().toString(36).substr(2, 9)}`,
        file,
        progress: 0,
        status: 'pending',
        previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined,
    }));
    files.value.push(...newFiles);
};

const removeFile = (id: string) => {
    const fileToRemove = files.value.find(f => f.id === id);
    if (fileToRemove?.previewUrl) {
        URL.revokeObjectURL(fileToRemove.previewUrl);
    }
    files.value = files.value.filter(f => f.id !== id);
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    addFiles(event.dataTransfer?.files || null);
};

const onFileSelected = () => {
    addFiles(fileInput.value?.files || null);
    if(fileInput.value) fileInput.value.value = '';
};

const openFileDialog = () => {
    if (isUploading.value) return;
    fileInput.value?.click();
};

const previewFile = (file: UploadableFile) => {
    fileToPreview.value = file;
    isPreviewOpen.value = true;
};

const getPendingFiles = () => files.value.filter(f => f.status === 'pending' || f.status === 'error');

const uploadPayslips = async () => {
    const filesToUpload = getPendingFiles();
    if (filesToUpload.length === 0) return;
    
    isUploading.value = true;

    const uploadPromises = filesToUpload.map(uploadableFile => {
        if (uploadableFile.status === 'error') {
            uploadableFile.progress = 0;
            uploadableFile.error = undefined;
        }
        
        return new Promise<void>((resolve) => {
            uploadableFile.status = 'uploading';
            const formData = new FormData();
            formData.append('file', uploadableFile.file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/upload', true);

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    uploadableFile.progress = (event.loaded / event.total) * 100;
                }
            };
            
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    uploadableFile.status = 'success';
                    uploadableFile.error = undefined;
                    try {
                        const response = JSON.parse(xhr.responseText);
                        uploadableFile.job_id = response.job_id;
                    } catch(e) {
                        uploadableFile.job_id = `job_${Math.random().toString(36).substr(2, 9)}`;
                    }
                } else {
                    uploadableFile.status = 'error';
                     try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            uploadableFile.error = response.message;
                            if (response.errors && response.errors.file && Array.isArray(response.errors.file)) {
                                uploadableFile.error = response.errors.file.join(', ');
                            }
                        } else {
                            uploadableFile.error = 'An unknown error occurred.';
                        }
                    } catch(e) {
                        uploadableFile.error = `Server returned status ${xhr.status}.`;
                    }
                }
                resolve();
            };

            xhr.onerror = () => {
                uploadableFile.status = 'error';
                uploadableFile.error = 'Network error during upload.'
                resolve();
            };

            xhr.send(formData);
        });
    });

    await Promise.all(uploadPromises);

    const successfullyUploaded = files.value.filter(f => f.status === 'success');
    if (successfullyUploaded.length > 0) {
        const newQueuedFiles: QueuedFile[] = successfullyUploaded.map(f => {
            if (f.previewUrl) URL.revokeObjectURL(f.previewUrl);
            return {
                id: parseInt(f.job_id!),
                name: f.file.name,
                size: f.file.size,
                status: 'queued',
                job_id: f.job_id!,
                created_at: new Date().toISOString(),
            }
        });

        queuedFiles.value.unshift(...newQueuedFiles);
        files.value = files.value.filter(f => f.status !== 'success');
        
        // Refresh statistics
        await fetchStatistics();
    }
    
    isUploading.value = false;
};

const statusClass = (status: QueuedFile['status']) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400';
        case 'failed': return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-400';
        case 'processing': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-400 animate-pulse';
        case 'queued':
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-400';
    }
};

const fetchQueuedFiles = async () => {
    try {
        const response = await fetch('/api/queue');
        if (response.ok) {
            queuedFiles.value = await response.json();
        }
    } catch (e) {
        console.error("Could not fetch queued files", e);
    }
};

const fetchStatistics = async () => {
    try {
        const response = await fetch('/api/payslips/statistics');
        if (response.ok) {
            statistics.value = await response.json();
        }
    } catch (e) {
        console.error("Could not fetch statistics", e);
    }
};

const refreshQueue = async () => {
    isRefreshing.value = true;
    await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    isRefreshing.value = false;
};

const deletePayslip = async (id: number) => {
    try {
        const response = await fetch(`/api/payslips/${id}`, {
            method: 'DELETE',
        });
        
        if (response.ok) {
            queuedFiles.value = queuedFiles.value.filter(f => f.id !== id);
            await fetchStatistics();
        }
    } catch (e) {
        console.error("Could not delete payslip", e);
    }
};

const clearCompleted = async () => {
    isClearing.value = true;
    try {
        const response = await fetch('/api/queue/clear', {
            method: 'DELETE',
        });
        
        if (response.ok) {
            await refreshQueue();
        }
    } catch (e) {
        console.error("Could not clear queue", e);
    }
    isClearing.value = false;
};

const clearAll = async () => {
    if (!confirm('Are you sure you want to clear the processing queue? This will not affect your permanent history.')) {
        return;
    }
    
    isClearing.value = true;
    try {
        const response = await fetch('/api/queue/clear', {
            method: 'DELETE',
        });
        
        if (response.ok) {
            queuedFiles.value = [];
            await fetchStatistics();
        }
    } catch (e) {
        console.error("Could not clear queue", e);
    }
    isClearing.value = false;
};

onMounted(async () => {
    await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    
    // Poll for updates every 3 seconds
    pollingInterval = setInterval(async () => {
        await Promise.all([fetchQueuedFiles(), fetchStatistics()]);
    }, 3000);
});

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Clean up preview URLs
    files.value.forEach(file => {
        if (file.previewUrl) {
            URL.revokeObjectURL(file.previewUrl);
        }
    });
});
</script>
