<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Package class="h-5 w-5" />
                Batch Uploader
            </CardTitle>
            <CardDescription>Upload multiple payslips for batch processing with advanced options.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Batch Settings -->
            <Collapsible v-model:open="showSettings">
                <CollapsibleTrigger asChild>
                    <Button variant="outline" class="w-full justify-between">
                        <span class="flex items-center gap-2">
                            <Settings class="h-4 w-4" />
                            Batch Processing Settings
                        </span>
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent class="space-y-4 mt-4 p-4 border rounded-lg bg-muted/50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label for="batch-name">Batch Name</Label>
                            <input
                                id="batch-name"
                                v-model="batchSettings.name"
                                type="text"
                                placeholder="Enter batch name (optional)"
                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div>
                            <Label for="priority">Processing Priority</Label>
                            <select
                                id="priority"
                                v-model="batchSettings.priority"
                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="low">Low Priority</option>
                                <option value="normal">Normal Priority</option>
                                <option value="high">High Priority</option>
                            </select>
                        </div>
                        <div>
                            <Label for="max-concurrent">Max Concurrent Processing</Label>
                            <input
                                id="max-concurrent"
                                v-model.number="batchSettings.maxConcurrent"
                                type="number"
                                min="1"
                                max="10"
                                class="w-full mt-1 px-3 py-2 border border-input rounded-md bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div class="flex items-center space-x-2">
                            <Checkbox
                                id="parallel-processing"
                                :checked="batchSettings.parallelProcessing"
                                @update:checked="batchSettings.parallelProcessing = $event"
                            />
                            <Label for="parallel-processing">Enable Parallel Processing</Label>
                        </div>
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <!-- File Drop Zone -->
            <div
                @dragover.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop.prevent="onDrop"
                :class="[
                    'flex flex-col items-center justify-center p-12 border-2 border-dashed rounded-lg cursor-pointer transition-colors',
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
                <Package class="w-12 h-12 text-muted-foreground" />
                <p class="mt-4 text-muted-foreground">Click or drag multiple files here for batch upload</p>
                <p class="mt-2 text-xs text-muted-foreground">
                    Supports PDF, PNG, JPG, JPEG (2-50 files, max 5MB each)
                </p>
            </div>

            <!-- File List -->
            <div v-if="files.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Files to Upload ({{ files.length }})</h3>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="clearFiles" :disabled="isUploading">
                            <Trash2 class="w-4 h-4 mr-2" />
                            Clear All
                        </Button>
                        <Button variant="outline" size="sm" @click="removeFailedFiles" :disabled="isUploading">
                            <XCircle class="w-4 h-4 mr-2" />
                            Remove Failed
                        </Button>
                        <Button variant="outline" size="sm" @click="resetFiles" :disabled="isUploading">
                            <RotateCcw class="w-4 h-4 mr-2" />
                            Reset Files
                        </Button>
                    </div>
                </div>

                <!-- File Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 max-h-96 overflow-y-auto">
                    <div
                        v-for="file in files"
                        :key="file.id"
                        class="relative group border rounded-lg overflow-hidden text-sm"
                    >
                        <div class="relative w-full h-24">
                            <img
                                v-if="file.previewUrl"
                                :src="file.previewUrl"
                                class="absolute inset-0 object-cover w-full h-full"
                                alt="File preview"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center bg-muted">
                                <FileText class="w-8 h-8 text-muted-foreground" />
                            </div>

                            <!-- Upload Progress -->
                            <div
                                v-if="file.status === 'uploading'"
                                class="absolute inset-0 bg-black/70 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <LoaderCircle class="w-5 h-5 animate-spin" />
                                <span class="text-xs font-medium">{{ file.progress.toFixed(0) }}%</span>
                            </div>

                            <!-- Upload Error -->
                            <div
                                v-if="file.status === 'error'"
                                class="absolute inset-0 bg-red-900/80 flex flex-col items-center justify-center gap-1 text-white p-1 text-center"
                            >
                                <XCircle class="w-5 h-5" />
                                <span class="text-xs font-semibold">Failed</span>
                            </div>

                            <!-- Upload Success -->
                            <div
                                v-if="file.status === 'success'"
                                class="absolute inset-0 bg-green-900/80 flex flex-col items-center justify-center gap-1 text-white"
                            >
                                <CheckCircle class="w-5 h-5" />
                                <span class="text-xs font-semibold">Uploaded</span>
                            </div>

                            <!-- File Actions -->
                            <div class="absolute top-1 right-1 z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    class="h-6 w-6"
                                    @click.stop="removeFile(file.id)"
                                    :disabled="isUploading"
                                >
                                    <X class="w-3 h-3" />
                                </Button>
                            </div>
                        </div>

                        <div class="p-2 border-t bg-card">
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
                <div class="flex items-center justify-between p-4 bg-muted rounded-lg">
                    <div class="text-sm">
                        <span class="font-medium">{{ getPendingFiles().length }}</span> files ready to upload
                        <span v-if="getFailedFiles().length > 0" class="text-red-500 ml-2">
                            ({{ getFailedFiles().length }} failed)
                        </span>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Total size: {{ getTotalSize() }}
                    </div>
                </div>

                <!-- Upload Button -->
                <Button
                    @click="uploadBatch"
                    class="w-full"
                    :disabled="isUploading || getPendingFiles().length < 2"
                    size="lg"
                >
                    <LoaderCircle v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
                    <Package v-else class="w-4 h-4 mr-2" />
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
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import {
    Package, Settings, ChevronDown, FileText, LoaderCircle, XCircle, CheckCircle,
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
const showSettings = ref(false)
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