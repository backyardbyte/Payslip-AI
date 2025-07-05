<template>
    <Card class="overflow-hidden border-2 border-dashed border-primary/20 hover:border-primary/40 transition-all duration-300">
        <CardHeader class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/50 dark:to-purple-950/50 pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="p-2 bg-primary/10 rounded-full">
                        <Package class="h-4 w-4 text-primary" />
                    </div>
                    <div>
                        <CardTitle class="text-base">Enhanced Batch Upload</CardTitle>
                        <CardDescription class="text-xs">
                            Upload multiple payslips simultaneously for efficient AI processing
                        </CardDescription>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-primary">{{ files.length }}</div>
                    <div class="text-xs text-muted-foreground">files selected</div>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Batch Settings -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 bg-muted/30 rounded-lg">
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Batch Name</Label>
                    <Input 
                        v-model="batchSettings.name" 
                        placeholder="Enter batch name"
                        class="text-xs h-7"
                    />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Processing Priority</Label>
                    <select
                        v-model="batchSettings.priority"
                        class="w-full px-2 py-1 border border-input rounded-md bg-background text-xs focus:outline-none focus:ring-2 focus:ring-ring h-7"
                    >
                        <option value="low">🔵 Low Priority</option>
                        <option value="normal">🟢 Normal Priority</option>
                        <option value="high">🟠 High Priority</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs font-medium">Auto-process</Label>
                    <div class="flex items-center space-x-2">
                        <Checkbox 
                            id="auto-process"
                            :checked="batchSettings.parallelProcessing"
                            @update:checked="batchSettings.parallelProcessing = $event"
                            class="h-3 w-3"
                        />
                        <Label for="auto-process" class="text-xs">Start processing immediately</Label>
                    </div>
                </div>
            </div>

            <!-- File Drop Zone -->
            <div
                @dragover.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
                :class="[
                    'flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-lg cursor-pointer transition-colors',
                    isDragging ? 'border-primary bg-primary/10' : 'border-border',
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
                <Package class="w-8 h-8 text-muted-foreground" />
                <p class="mt-3 text-sm text-muted-foreground">Click or drag multiple files here for batch upload</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Supports PDF, PNG, JPG, JPEG (2-50 files, max 5MB each)
                </p>
            </div>

            <!-- File List -->
            <div v-if="files.length > 0" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Files to Upload ({{ files.length }})</h3>
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
                            Reset Files
                        </Button>
                    </div>
                </div>

                <!-- File Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="file in files"
                        :key="file.id"
                        class="relative group border rounded-lg overflow-hidden text-sm"
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
                                class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <LoaderCircle class="w-4 h-4 animate-spin" />
                                <span class="text-xs font-medium">{{ file.progress.toFixed(0) }}%</span>
                            </div>

                            <!-- Upload Error -->
                            <div
                                v-if="file.status === 'error'"
                                class="absolute inset-0 bg-red-900/80 flex flex-col items-center justify-center gap-1 text-white p-1 text-center"
                            >
                                <XCircle class="w-4 h-4" />
                                <span class="text-xs font-semibold">Failed</span>
                            </div>

                            <!-- Upload Success -->
                            <div
                                v-if="file.status === 'success'"
                                class="absolute inset-0 bg-green-900/80 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <CheckCircle class="w-4 h-4" />
                                <span class="text-xs font-semibold">Uploaded</span>
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
                <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
                    <div class="text-xs">
                        <span class="font-medium">{{ getPendingFiles().length }}</span> files ready to upload
                        <span v-if="getFailedFiles().length > 0" class="text-red-500 ml-2">
                            ({{ getFailedFiles().length }} failed)
                        </span>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Total size: {{ getTotalSize() }}
                    </div>
                </div>

                <!-- Upload Button -->
                <Button
                    @click="uploadBatch"
                    class="w-full h-7 text-xs"
                    :disabled="isUploading || getPendingFiles().length < 2"
                    size="lg"
                >
                    <LoaderCircle v-if="isUploading" class="w-3 h-3 mr-1.5 animate-spin" />
                    <Package v-else class="w-3 h-3 mr-1.5" />
                    {{ isUploading ? 'Uploading Batch...' : `Upload Batch (${getPendingFiles().length} files)` }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'

import {
    Package, FileText, LoaderCircle, XCircle, CheckCircle,
    X, Trash2, RotateCcw
} from 'lucide-vue-next'

interface BatchFile {
    id: string
    file: File
    progress: number
    status: 'pending' | 'uploading' | 'success' | 'error'
    error?: string
    previewUrl?: string
}

interface BatchSettings {
    name: string
    priority: 'low' | 'normal' | 'high'
    maxConcurrent: number
    parallelProcessing: boolean
}

const emit = defineEmits<{
    batchUploaded: [result: any]
}>()

const files = ref<BatchFile[]>([])
const isDragging = ref(false)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const batchSettings = ref<BatchSettings>({
    name: '',
    priority: 'normal',
    maxConcurrent: 5,
    parallelProcessing: true,
})

const onDragOver = () => { isDragging.value = true }
const onDragLeave = () => { isDragging.value = false }

const addFiles = (fileList: FileList | null) => {
    if (!fileList) return

    const newFiles: BatchFile[] = Array.from(fileList).map(file => ({
        id: `file_${Math.random().toString(36).substr(2, 9)}`,
        file,
        progress: 0,
        status: 'pending',
        previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined,
    }))

    files.value.push(...newFiles)
}

const removeFile = (id: string) => {
    const fileToRemove = files.value.find(f => f.id === id)
    if (fileToRemove?.previewUrl) {
        URL.revokeObjectURL(fileToRemove.previewUrl)
    }
    files.value = files.value.filter(f => f.id !== id)
}

const clearFiles = () => {
    files.value.forEach(file => {
        if (file.previewUrl) {
            URL.revokeObjectURL(file.previewUrl)
        }
    })
    files.value = []
}

const removeFailedFiles = () => {
    const failedFiles = files.value.filter(f => f.status === 'error')
    failedFiles.forEach(file => {
        if (file.previewUrl) {
            URL.revokeObjectURL(file.previewUrl)
        }
    })
    files.value = files.value.filter(f => f.status !== 'error')
}

const resetFiles = () => {
    files.value.forEach(file => {
        file.status = 'pending'
        file.progress = 0
        file.error = undefined
    })
}

const onDrop = (event: DragEvent) => {
    isDragging.value = false
    addFiles(event.dataTransfer?.files || null)
}

const onFileSelected = () => {
    addFiles(fileInput.value?.files || null)
    if (fileInput.value) fileInput.value.value = ''
}

const openFileDialog = () => {
    if (isUploading.value) return
    fileInput.value?.click()
}

const getPendingFiles = () => files.value.filter(f => f.status === 'pending' || f.status === 'error')
const getFailedFiles = () => files.value.filter(f => f.status === 'error')

const getTotalSize = () => {
    const totalBytes = files.value.reduce((sum, file) => sum + file.file.size, 0)
    const totalMB = totalBytes / (1024 * 1024)
    return totalMB < 1 ? `${(totalBytes / 1024).toFixed(1)} KB` : `${totalMB.toFixed(1)} MB`
}

const uploadBatch = async () => {
    const filesToUpload = getPendingFiles()
    if (filesToUpload.length < 2) return

    isUploading.value = true

    try {
        const formData = new FormData()
        
        // Add files
        filesToUpload.forEach((batchFile, index) => {
            formData.append('files[]', batchFile.file)
            batchFile.status = 'uploading'
            batchFile.progress = 0
        })

        // Add batch settings
        if (batchSettings.value.name) {
            formData.append('batch_name', batchSettings.value.name)
        }

        formData.append('settings[priority]', batchSettings.value.priority)
        formData.append('settings[max_concurrent]', batchSettings.value.maxConcurrent.toString())
        formData.append('settings[parallel_processing]', batchSettings.value.parallelProcessing ? '1' : '0')

        const xhr = new XMLHttpRequest()
        xhr.open('POST', '/api/batch/upload', true)

        // Track overall progress
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                const progress = (event.loaded / event.total) * 100
                filesToUpload.forEach(file => {
                    file.progress = progress
                })
            }
        }

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                const response = JSON.parse(xhr.responseText)
                
                // Mark all files as successful
                filesToUpload.forEach(file => {
                    file.status = 'success'
                    file.progress = 100
                })

                emit('batchUploaded', response.data)
                
                // Clear successful files after a delay
                setTimeout(() => {
                    const successfulFiles = files.value.filter(f => f.status === 'success')
                    successfulFiles.forEach(file => {
                        if (file.previewUrl) URL.revokeObjectURL(file.previewUrl)
                    })
                    files.value = files.value.filter(f => f.status !== 'success')
                }, 2000)

            } else {
                // Handle error
                const response = JSON.parse(xhr.responseText)
                filesToUpload.forEach(file => {
                    file.status = 'error'
                    file.error = response.message || 'Upload failed'
                })
            }
        }

        xhr.onerror = () => {
            filesToUpload.forEach(file => {
                file.status = 'error'
                file.error = 'Network error during upload'
            })
        }

        xhr.send(formData)

    } catch (error) {
        filesToUpload.forEach(file => {
            file.status = 'error'
            file.error = 'Upload failed'
        })
    } finally {
        isUploading.value = false
    }
}

// Clean up preview URLs when component unmounts
onUnmounted(() => {
    files.value.forEach(file => {
        if (file.previewUrl) {
            URL.revokeObjectURL(file.previewUrl)
        }
    })
})
</script> 