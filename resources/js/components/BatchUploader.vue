<template>
    <Card class="overflow-hidden border-2 border-dashed border-primary/20 hover:border-primary/40 transition-all duration-300">
        <CardHeader class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/50 dark:to-purple-950/50 pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-primary/10 rounded-full">
                        <Package class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <CardTitle class="text-lg font-semibold">AI Batch Processing</CardTitle>
                        <CardDescription class="text-sm flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full dark:bg-blue-900/50 dark:text-blue-200">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                                </svg>
                                Google Vision API
                            </span>
                            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full dark:bg-green-900/50 dark:text-green-200">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                                </svg>
                                Batch Processing
                            </span>
                        </CardDescription>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-primary">{{ files.length }}</div>
                    <div class="text-xs text-muted-foreground">files queued</div>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- AI Processing Showcase -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 bg-blue-500/10 rounded-full">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-blue-900 dark:text-blue-100">Parallel AI Processing</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300">Process multiple payslips simultaneously with Google Vision API</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">5x Faster</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">Smart Queue</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">Batch Reports</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium">Live Analytics</span>
                    </div>
                </div>
            </div>

            <!-- Batch Settings -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-4 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-950/50 dark:to-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-800">
                <div class="space-y-1">
                    <Label class="text-xs font-medium flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        Batch Name
                    </Label>
                    <Input 
                        v-model="batchSettings.name" 
                        placeholder="e.g., Monthly Payslips"
                        class="text-xs h-7"
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        AI Processing Priority
                    </Label>
                    <select
                        v-model="batchSettings.priority"
                        class="w-full px-2 py-1 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-2 focus:ring-ring h-7"
                    >
                        <option value="low">🔵 Low Priority (Background)</option>
                        <option value="normal">🟢 Normal Priority (Standard)</option>
                        <option value="high">🔴 High Priority (Urgent)</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Auto-process
                    </Label>
                    <div class="flex items-center space-x-2">
                        <Checkbox 
                            id="auto-process"
                            :checked="batchSettings.parallelProcessing"
                            @update:checked="batchSettings.parallelProcessing = $event"
                            class="h-3 w-3"
                        />
                        <Label for="auto-process" class="text-xs">Start Google Vision API processing immediately</Label>
                    </div>
                </div>
            </div>

            <!-- File Drop Zone -->
            <div
                @dragover.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
                :class="[
                    'flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-lg cursor-pointer transition-all duration-200',
                    isDragging ? 'border-primary bg-primary/10 scale-[1.02]' : 'border-border hover:border-primary/50 hover:bg-muted/50',
                    isUploading ? 'pointer-events-none opacity-50' : ''
                ]"
                @click="openFileDialog"
            >
                <input
                    type="file"
                    ref="fileInput"
                    @change="onFileSelected"
                    multiple
                    class="hidden"
                    accept=".pdf,.png,.jpg,.jpeg"
                />
                <div class="relative">
                    <Package class="w-12 h-12 text-muted-foreground" />
                    <div v-if="isDragging" class="absolute inset-0 animate-ping">
                        <Package class="w-12 h-12 text-primary opacity-75" />
                    </div>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-sm font-medium">
                        {{ isDragging ? 'Drop files here for batch AI processing' : 'Click or drag multiple payslips here' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Supports PDF, PNG, JPG, JPEG (2-50 files, max 10MB each)
                    </p>
                    <p class="mt-0.5 text-xs text-blue-600 dark:text-blue-400 font-medium">
                        ⚡ Parallel processing with Google Vision API
                    </p>
                </div>
            </div>

            <!-- File List -->
            <div v-if="files.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold">Files for AI Processing</h3>
                        <Badge variant="secondary" class="text-xs">{{ files.length }} files</Badge>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="clearFiles" :disabled="isUploading" class="h-7 text-xs">
                            <Trash2 class="w-3 h-3 mr-1.5" />
                            Clear All
                        </Button>
                        <Button variant="outline" size="sm" @click="removeFailedFiles" :disabled="isUploading" class="h-7 text-xs">
                            <XCircle class="w-3 h-3 mr-1.5" />
                            Remove Failed
                        </Button>
                        <Button variant="outline" size="sm" @click="resetFiles" :disabled="isUploading" class="h-7 text-xs">
                            <RotateCcw class="w-3 h-3 mr-1.5" />
                            Reset Status
                        </Button>
                    </div>
                </div>

                <!-- File Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="file in files"
                        :key="file.id"
                        class="relative group border rounded-lg overflow-hidden text-sm hover:shadow-md transition-all duration-200"
                    >
                        <div class="relative w-full h-20">
                            <img
                                v-if="file.previewUrl"
                                :src="file.previewUrl"
                                class="absolute inset-0 object-cover w-full h-full"
                                alt="File preview"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center bg-muted">
                                <FileText class="w-6 h-6 text-muted-foreground" />
                            </div>

                            <!-- Upload Progress -->
                            <div
                                v-if="file.status === 'uploading'"
                                class="absolute inset-0 bg-gradient-to-r from-blue-500/90 to-purple-500/90 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <LoaderCircle class="w-4 h-4 animate-spin" />
                                <span class="text-xs font-medium">AI Processing</span>
                                <span class="text-xs">{{ file.progress.toFixed(0) }}%</span>
                            </div>

                            <!-- Upload Error -->
                            <div
                                v-if="file.status === 'error'"
                                class="absolute inset-0 bg-red-500/90 flex flex-col items-center justify-center gap-1 text-white p-1 text-center"
                            >
                                <XCircle class="w-4 h-4" />
                                <span class="text-xs font-semibold">Failed</span>
                            </div>

                            <!-- Upload Success -->
                            <div
                                v-if="file.status === 'success'"
                                class="absolute inset-0 bg-green-500/90 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <CheckCircle class="w-4 h-4" />
                                <span class="text-xs font-semibold">Processed</span>
                            </div>

                            <!-- File Actions -->
                            <div class="absolute top-1 right-1 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    class="h-5 w-5"
                                    @click.stop="removeFile(file.id)"
                                    :disabled="isUploading"
                                >
                                    <X class="w-3 h-3" />
                                </Button>
                            </div>
                        </div>

                        <div class="p-1.5 border-t bg-card">
                            <p class="font-medium truncate text-xs" :title="file.file.name">
                                {{ file.file.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ (file.file.size / 1024).toFixed(1) }} KB
                            </p>
                            <p v-if="file.error" class="text-xs text-red-500 mt-1" :title="file.error">
                                {{ file.error.substring(0, 30) }}{{ file.error.length > 30 ? '...' : '' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Upload Summary -->
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex items-center gap-4">
                        <div class="text-sm">
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-medium text-blue-900 dark:text-blue-100">{{ getPendingFiles.length }}</span>
                                <span class="text-blue-700 dark:text-blue-300">files ready for AI processing</span>
                            </div>
                            <div v-if="getFailedFiles.length > 0" class="flex items-center gap-1 mt-1">
                                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-red-500">{{ getFailedFiles.length }} failed</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ getTotalSize }}</div>
                        <div class="text-xs text-blue-700 dark:text-blue-300">Total batch size</div>
                    </div>
                </div>

                <!-- Upload Button -->
                <Button
                    @click="uploadBatch"
                    class="w-full h-10 text-sm bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600"
                    :disabled="isUploading || getPendingFiles.length < 2"
                    size="lg"
                >
                    <LoaderCircle v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
                    <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ isUploading ? 'Processing with Google Vision API...' : `Process ${getPendingFiles.length} Files with AI` }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Package,
    FileText,
    LoaderCircle,
    CheckCircle,
    XCircle,
    X,
    Trash2,
    RotateCcw
} from 'lucide-vue-next'

interface BatchFile {
    id: string
    file: File
    status: 'pending' | 'uploading' | 'success' | 'error'
    progress: number
    error?: string
    previewUrl?: string
}

interface BatchSettings {
    name: string
    priority: 'low' | 'normal' | 'high'
    parallelProcessing: boolean
}

// Props
interface Props {
    maxFiles?: number
    maxFileSize?: number
}

const props = withDefaults(defineProps<Props>(), {
    maxFiles: 50,
    maxFileSize: 10
})

// Emits
const emit = defineEmits<{
    batchUploaded: [result: any]
}>()

// Reactive state
const files = ref<BatchFile[]>([])
const isDragging = ref(false)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement>()

const batchSettings = ref<BatchSettings>({
    name: '',
    priority: 'normal',
    parallelProcessing: true
})

// Computed
const getPendingFiles = computed(() => {
    return files.value.filter(f => f.status === 'pending')
})

const getFailedFiles = computed(() => {
    return files.value.filter(f => f.status === 'error')
})

const getTotalSize = computed(() => {
    const totalBytes = files.value.reduce((sum, file) => sum + file.file.size, 0)
    if (totalBytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(totalBytes) / Math.log(k))
    return parseFloat((totalBytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
})

// Methods
const onDragOver = () => {
    isDragging.value = true
}

const onDragLeave = () => {
    isDragging.value = false
}

const onDrop = (event: DragEvent) => {
    isDragging.value = false
    const droppedFiles = Array.from(event.dataTransfer?.files || [])
    handleFiles(droppedFiles)
}

const openFileDialog = () => {
    if (isUploading.value) return
    fileInput.value?.click()
}

const onFileSelected = () => {
    const selectedFiles = fileInput.value?.files
    if (selectedFiles) {
        handleFiles(Array.from(selectedFiles))
    }
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const handleFiles = (fileList: File[]) => {
    fileList.forEach(file => {
        // Validate file type
        const allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg']
        if (!allowedTypes.includes(file.type)) {
            alert(`File type not supported: ${file.type}`)
            return
        }

        // Validate file size
        if (file.size > props.maxFileSize * 1024 * 1024) {
            alert(`File too large: ${file.name} (max ${props.maxFileSize}MB)`)
            return
        }

        // Check max files
        if (files.value.length >= props.maxFiles) {
            alert(`Maximum ${props.maxFiles} files allowed`)
            return
        }

        const batchFile: BatchFile = {
            id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
            file,
            status: 'pending',
            progress: 0
        }

        // Create preview for images
        if (file.type.startsWith('image/')) {
            const reader = new FileReader()
            reader.onload = (e) => {
                batchFile.previewUrl = e.target?.result as string
            }
            reader.readAsDataURL(file)
        }

        files.value.push(batchFile)
    })
}

const removeFile = (fileId: string) => {
    files.value = files.value.filter(f => f.id !== fileId)
}

const clearFiles = () => {
    files.value = []
}

const removeFailedFiles = () => {
    files.value = files.value.filter(f => f.status !== 'error')
}

const resetFiles = () => {
    files.value.forEach(file => {
        if (file.status === 'error') {
            file.status = 'pending'
            file.error = undefined
            file.progress = 0
        }
    })
}

const uploadBatch = async () => {
    if (getPendingFiles.value.length < 2) return

    isUploading.value = true

    try {
        const formData = new FormData()
        
        // Add batch settings
        formData.append('batch_name', batchSettings.value.name || `Batch ${new Date().toISOString().slice(0, 10)}`)
        formData.append('settings[priority]', batchSettings.value.priority)
        formData.append('settings[parallel_processing]', batchSettings.value.parallelProcessing.toString())
        
        // Add files
        getPendingFiles.value.forEach((file, index) => {
            formData.append(`files[]`, file.file)
        })

        // Update all pending files to uploading
        getPendingFiles.value.forEach(file => {
            file.status = 'uploading'
            file.progress = 0
        })

        // Simulate progress
        const progressInterval = setInterval(() => {
            files.value.forEach(file => {
                if (file.status === 'uploading' && file.progress < 90) {
                    file.progress += Math.random() * 10
                }
            })
        }, 200)

        const response = await fetch('/api/batch/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })

        clearInterval(progressInterval)

        if (response.ok) {
            const result = await response.json()
            
            // Update all uploading files to success
            files.value.forEach(file => {
                if (file.status === 'uploading') {
                    file.status = 'success'
                    file.progress = 100
                }
            })

            emit('batchUploaded', result)
        } else {
            const error = await response.json()
            
            // Update all uploading files to error
            files.value.forEach(file => {
                if (file.status === 'uploading') {
                    file.status = 'error'
                    file.error = error.message || 'Upload failed'
                }
            })
        }
    } catch (error) {
        // Update all uploading files to error
        files.value.forEach(file => {
            if (file.status === 'uploading') {
                file.status = 'error'
                file.error = error instanceof Error ? error.message : 'Upload failed'
            }
        })
    } finally {
        isUploading.value = false
    }
}
</script> 