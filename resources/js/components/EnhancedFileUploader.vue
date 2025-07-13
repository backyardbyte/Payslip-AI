<template>
  <Card class="overflow-hidden border-2 border-dashed border-primary/20 hover:border-primary/40 transition-all duration-300">
    <CardHeader class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/50 dark:to-purple-950/50 pb-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="flex items-center space-x-2">
            <div class="p-2 bg-primary/10 rounded-full">
              <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 12l2 2 4-4"/>
                <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                <path d="M12 3c0 1-1 3-3 3s-3-2-3-3 1-3 3-3 3 2 3 3"/>
                <path d="M12 21c0-1 1-3 3-3s3 2 3 3-1 3-3 3-3-2-3-3"/>
              </svg>
            </div>
            <div>
              <CardTitle class="text-lg font-semibold">AI-Powered Payslip Uploader</CardTitle>
              <CardDescription class="text-sm flex items-center gap-2">
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
              </CardDescription>
            </div>
          </div>
        </div>
        <div class="text-right">
          <div class="text-2xl font-bold text-primary">{{ files.length }}</div>
          <div class="text-xs text-muted-foreground">files ready</div>
        </div>
      </div>
    </CardHeader>
    <CardContent class="space-y-4">
      <!-- AI Technology Showcase -->
      <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3 mb-3">
          <div class="p-2 bg-blue-500/10 rounded-full">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
          </div>
          <div>
            <h3 class="font-semibold text-blue-900 dark:text-blue-100">Advanced AI Processing</h3>
            <p class="text-sm text-blue-700 dark:text-blue-300">Powered by Google Cloud Vision API for superior text recognition</p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <span class="text-sm font-medium">99% Accuracy</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
            </div>
            <span class="text-sm font-medium">Smart Extraction</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
              </svg>
            </div>
            <span class="text-sm font-medium">Real-time Analysis</span>
          </div>
        </div>
      </div>

      <!-- File Drop Zone -->
      <div
        @dragover.prevent="onDragOver"
        @dragleave.prevent="onDragLeave"
        @drop.prevent="onDrop"
        :class="[
          'relative flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-lg cursor-pointer transition-all duration-200',
          isDragging 
            ? 'border-primary bg-primary/10 scale-[1.02]' 
            : 'border-border hover:border-primary/50 hover:bg-muted/50'
        ]"
        @click="openFileDialog"
      >
        <input 
          type="file" 
          ref="fileInput" 
          @change="onFileSelected" 
          multiple 
          class="hidden" 
          :accept="allowedFileTypes.map(type => '.' + type).join(',')"
        />
        
        <!-- Upload Icon with Animation -->
        <div class="relative">
          <UploadCloud 
            :class="[
              'w-12 h-12 transition-all duration-300',
              isDragging ? 'text-primary scale-110' : 'text-muted-foreground'
            ]" 
          />
          <div v-if="isDragging" class="absolute inset-0 animate-ping">
            <UploadCloud class="w-12 h-12 text-primary opacity-75" />
          </div>
        </div>
        
        <!-- Upload Text -->
        <div class="mt-4 text-center">
          <p class="text-sm font-medium">
            {{ isDragging ? 'Drop files here for AI processing' : 'Click or drag payslips here' }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Supports {{ allowedFileTypes.join(', ').toUpperCase() }} files up to {{ maxFileSize }}MB each
          </p>
          <p class="mt-0.5 text-xs text-blue-600 dark:text-blue-400 font-medium">
            ⚡ Instant processing with Google Vision API
          </p>
        </div>
        
        <!-- Upload Progress Overlay -->
        <div v-if="isUploading" class="absolute inset-0 bg-background/80 flex items-center justify-center rounded-lg">
          <div class="text-center">
            <div class="flex items-center justify-center gap-2 mb-2">
              <LoaderCircle class="w-5 h-5 animate-spin text-primary" />
              <span class="text-sm font-medium">AI Processing...</span>
            </div>
            <p class="text-xs text-muted-foreground">{{ uploadProgress.current }} of {{ uploadProgress.total }} files</p>
            <div class="mt-2 w-32 bg-muted rounded-full h-2">
              <div 
                class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full transition-all duration-300"
                :style="{ width: uploadProgress.percentage + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- File Preview Section -->
      <div v-if="files.length > 0" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-medium">Selected Files</h3>
            <Badge variant="secondary" class="text-xs">{{ files.length }} files</Badge>
          </div>
          <div class="flex gap-2">
            <Button variant="outline" size="sm" @click="clearAll" :disabled="isUploading" class="h-7 text-xs">
              <Trash2 class="w-3 h-3 mr-1.5" />
              Clear All
            </Button>
            <Button 
              @click="uploadFiles" 
              :disabled="isUploading || getPendingFiles().length === 0"
              class="bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 h-7 text-xs"
            >
              <LoaderCircle v-if="isUploading" class="w-3 h-3 mr-1.5 animate-spin" />
              <svg v-else class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              {{ isUploading ? 'Processing with AI...' : `Process ${getPendingFiles().length} File(s)` }}
            </Button>
          </div>
        </div>
        
        <!-- File Grid -->
        <div class="grid grid-cols-5 sm:grid-cols-7 lg:grid-cols-10 gap-2">
          <div
            v-for="file in files"
            :key="file.id"
            class="group relative bg-card border rounded-lg overflow-hidden hover:shadow-md transition-all duration-200"
          >
            <!-- File Preview -->
            <div class="aspect-square bg-muted flex items-center justify-center relative">
              <!-- Image Preview -->
              <img
                v-if="file.previewUrl"
                :src="file.previewUrl"
                :alt="file.file.name"
                class="w-full h-full object-cover"
              />
              <!-- PDF Icon -->
              <div v-else class="flex flex-col items-center text-muted-foreground">
                <FileText class="w-5 h-5" />
                <span class="text-xs mt-0.5 font-medium">PDF</span>
              </div>
              
              <!-- Status Overlay -->
              <div 
                v-if="file.status !== 'pending'"
                :class="[
                  'absolute inset-0 flex items-center justify-center text-white font-medium',
                  file.status === 'uploading' ? 'bg-gradient-to-r from-blue-500/90 to-purple-500/90' : '',
                  file.status === 'success' ? 'bg-green-500/90' : '',
                  file.status === 'error' ? 'bg-red-500/90' : ''
                ]"
              >
                <div class="text-center">
                  <LoaderCircle v-if="file.status === 'uploading'" class="w-4 h-4 animate-spin mx-auto mb-1" />
                  <CheckCircle v-else-if="file.status === 'success'" class="w-4 h-4 mx-auto mb-1" />
                  <XCircle v-else-if="file.status === 'error'" class="w-4 h-4 mx-auto mb-1" />
                  <span v-if="file.status === 'uploading'" class="text-xs">AI Processing</span>
                  <span v-else-if="file.status === 'success'" class="text-xs">Ready</span>
                  <span v-else-if="file.status === 'error'" class="text-xs">Failed</span>
                </div>
              </div>
              
              <!-- Progress Bar -->
              <div 
                v-if="file.status === 'uploading'"
                class="absolute bottom-0 left-0 right-0 h-1 bg-white/20"
              >
                <div 
                  class="h-full bg-gradient-to-r from-blue-400 to-purple-400 transition-all duration-300"
                  :style="{ width: file.progress + '%' }"
                ></div>
              </div>
              
              <!-- Action Buttons -->
              <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity flex gap-0.5">
                <Button variant="secondary" size="icon" class="h-4 w-4" @click.stop="previewFile(file)">
                  <Eye class="w-2.5 h-2.5" />
                </Button>
                <Button 
                  variant="destructive" 
                  size="icon" 
                  class="h-4 w-4" 
                  @click.stop="removeFile(file.id)"
                  :disabled="file.status === 'uploading'"
                >
                  <Trash2 class="w-2.5 h-2.5" />
                </Button>
              </div>
            </div>
            
            <!-- File Info -->
            <div class="p-1.5 border-t bg-card">
              <p class="font-medium truncate text-xs leading-tight" :title="file.file.name">
                {{ file.file.name }}
              </p>
              <div class="flex items-center justify-between mt-0.5">
                <p class="text-xs text-muted-foreground">
                  {{ formatFileSize(file.file.size) }}
                </p>
                <Badge 
                  :variant="getStatusVariant(file.status)"
                  class="text-xs px-1 py-0"
                >
                  {{ getStatusText(file.status) }}
                </Badge>
              </div>
              
              <!-- Error Message -->
              <p v-if="file.error" class="text-xs text-red-500 mt-0.5 truncate" :title="file.error">
                {{ file.error }}
              </p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Upload Statistics -->
      <div v-if="uploadStats.total > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-950/30 dark:to-purple-950/30 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="text-center">
          <div class="text-xl font-bold text-blue-600">{{ uploadStats.total }}</div>
          <div class="text-xs text-muted-foreground">Total Files</div>
        </div>
        <div class="text-center">
          <div class="text-xl font-bold text-green-600">{{ uploadStats.success }}</div>
          <div class="text-xs text-muted-foreground">AI Processed</div>
        </div>
        <div class="text-center">
          <div class="text-xl font-bold text-red-600">{{ uploadStats.failed }}</div>
          <div class="text-xs text-muted-foreground">Failed</div>
        </div>
        <div class="text-center">
          <div class="text-xl font-bold text-orange-600">{{ uploadStats.pending }}</div>
          <div class="text-xs text-muted-foreground">Pending</div>
        </div>
      </div>
    </CardContent>
  </Card>
  
  <!-- File Preview Modal -->
  <Dialog v-model:open="isPreviewOpen">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-auto">
      <DialogHeader>
        <DialogTitle class="text-base flex items-center gap-2">
          <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          File Preview
        </DialogTitle>
        <DialogDescription v-if="fileToPreview" class="text-xs">
          {{ fileToPreview.file.name }} ({{ formatFileSize(fileToPreview.file.size) }})
          <span class="inline-flex items-center gap-1 ml-2 text-blue-600">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Ready for Google Vision API processing
          </span>
        </DialogDescription>
      </DialogHeader>
      <div v-if="fileToPreview" class="mt-3">
        <img
          v-if="fileToPreview.previewUrl"
          :src="fileToPreview.previewUrl"
          :alt="fileToPreview.file.name"
          class="w-full h-auto max-h-96 object-contain border rounded"
        />
        <div v-else class="flex items-center justify-center h-96 bg-muted rounded">
          <div class="text-center text-muted-foreground">
            <FileText class="w-12 h-12 mx-auto mb-3" />
            <p class="text-sm">PDF Preview Not Available</p>
            <p class="text-xs">Will be processed with Google Vision API after upload</p>
          </div>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { 
  UploadCloud, 
  LoaderCircle, 
  Eye, 
  Trash2, 
  FileText, 
  CheckCircle, 
  XCircle 
} from 'lucide-vue-next'

interface UploadableFile {
  id: string
  file: File
  progress: number
  status: 'pending' | 'uploading' | 'success' | 'error'
  previewUrl?: string
  error?: string
}

interface UploadProgress {
  current: number
  total: number
  percentage: number
}

// Props
interface Props {
  maxFileSize?: number
  allowedFileTypes?: string[]
  multiple?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  maxFileSize: 10,
  allowedFileTypes: () => ['pdf', 'png', 'jpg', 'jpeg'],
  multiple: true
})

// Emits
const emit = defineEmits<{
  upload: [files: File[]]
  fileAdded: [file: File]
  fileRemoved: [fileId: string]
  uploadComplete: [results: any[]]
}>()

// Reactive state
const files = ref<UploadableFile[]>([])
const isDragging = ref(false)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement>()
const isPreviewOpen = ref(false)
const fileToPreview = ref<UploadableFile | null>(null)

const uploadProgress = ref<UploadProgress>({
  current: 0,
  total: 0,
  percentage: 0
})

// Computed
const uploadStats = computed(() => {
  const stats = {
    total: files.value.length,
    success: 0,
    failed: 0,
    pending: 0
  }

  files.value.forEach(file => {
    if (file.status === 'success') stats.success++
    else if (file.status === 'error') stats.failed++
    else if (file.status === 'pending') stats.pending++
  })

  return stats
})

// Methods
const openFileDialog = () => {
  fileInput.value?.click()
}

const onFileSelected = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    handleFiles(Array.from(target.files))
  }
}

const onDragOver = (event: DragEvent) => {
  isDragging.value = true
}

const onDragLeave = (event: DragEvent) => {
  isDragging.value = false
}

const onDrop = (event: DragEvent) => {
  isDragging.value = false
  const files = Array.from(event.dataTransfer?.files || [])
  handleFiles(files)
}

const handleFiles = (fileList: File[]) => {
  fileList.forEach(file => {
    // Validate file type
    const fileExtension = file.name.split('.').pop()?.toLowerCase()
    if (!props.allowedFileTypes.includes(fileExtension || '')) {
      alert(`File type ${fileExtension} is not allowed`)
      return
    }

    // Validate file size
    if (file.size > props.maxFileSize * 1024 * 1024) {
      alert(`File ${file.name} exceeds maximum size of ${props.maxFileSize}MB`)
      return
    }

    const fileId = Date.now().toString() + Math.random().toString(36).substr(2, 9)
    const uploadableFile: UploadableFile = {
      id: fileId,
      file,
      progress: 0,
      status: 'pending'
    }

    // Create preview for images
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => {
        uploadableFile.previewUrl = e.target?.result as string
      }
      reader.readAsDataURL(file)
    }

    files.value.push(uploadableFile)
    emit('fileAdded', file)
  })
}

const removeFile = (fileId: string) => {
  const index = files.value.findIndex(f => f.id === fileId)
  if (index > -1) {
    files.value.splice(index, 1)
    emit('fileRemoved', fileId)
  }
}

const clearAll = () => {
  files.value = []
}

const getPendingFiles = () => {
  return files.value.filter(f => f.status === 'pending')
}

const uploadFiles = async () => {
  const pendingFiles = getPendingFiles()
  if (pendingFiles.length === 0) return

  isUploading.value = true
  uploadProgress.value.total = pendingFiles.length
  uploadProgress.value.current = 0

  const results = []

  for (const file of pendingFiles) {
    try {
      file.status = 'uploading'
      file.progress = 0

      // Simulate upload progress
      const progressInterval = setInterval(() => {
        if (file.progress < 90) {
          file.progress += Math.random() * 10
        }
      }, 100)

      // Upload file
      const formData = new FormData()
      formData.append('file', file.file)

      const response = await fetch('/api/upload', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      })

      clearInterval(progressInterval)
      file.progress = 100

      if (response.ok) {
        const result = await response.json()
        file.status = 'success'
        results.push(result)
      } else {
        file.status = 'error'
        const error = await response.json()
        file.error = error.message || 'Upload failed'
      }
    } catch (error) {
      file.status = 'error'
      file.error = error instanceof Error ? error.message : 'Upload failed'
    }

    uploadProgress.value.current++
    uploadProgress.value.percentage = (uploadProgress.value.current / uploadProgress.value.total) * 100
  }

  isUploading.value = false
  emit('uploadComplete', results)
}

const previewFile = (file: UploadableFile) => {
  fileToPreview.value = file
  isPreviewOpen.value = true
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const getStatusVariant = (status: string) => {
  switch (status) {
    case 'success': return 'default'
    case 'error': return 'destructive'
    case 'uploading': return 'secondary'
    default: return 'outline'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'success': return 'Ready'
    case 'error': return 'Failed'
    case 'uploading': return 'AI Processing'
    default: return 'Pending'
  }
}

// Cleanup
onUnmounted(() => {
  files.value.forEach(file => {
    if (file.previewUrl) {
      URL.revokeObjectURL(file.previewUrl)
    }
  })
})
</script> 