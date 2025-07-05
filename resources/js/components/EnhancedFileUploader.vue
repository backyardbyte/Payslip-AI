<template>
  <Card>
    <CardHeader class="pb-3">
      <CardTitle class="flex items-center gap-2 text-base">
        <UploadCloud class="h-4 w-4" />
        Enhanced Payslip Uploader
      </CardTitle>
      <CardDescription class="text-xs">
        Drag and drop your payslips below or click to select files.
        Supports PDF, PNG, JPG, JPEG (max {{ maxFileSize }}MB)
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
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
        <div class="mt-3 text-center">
          <p class="text-sm font-medium">
            {{ isDragging ? 'Drop files here' : 'Click or drag files here to upload' }}
          </p>
          <p class="mt-1 text-xs text-muted-foreground">
            Supports {{ allowedFileTypes.join(', ').toUpperCase() }} files up to {{ maxFileSize }}MB each
          </p>
          <p class="mt-0.5 text-xs text-muted-foreground">
            Multiple files supported • Real-time processing
          </p>
        </div>
        
        <!-- Upload Progress Overlay -->
        <div v-if="isUploading" class="absolute inset-0 bg-background/80 flex items-center justify-center rounded-lg">
          <div class="text-center">
            <LoaderCircle class="w-6 h-6 animate-spin mx-auto text-primary" />
            <p class="mt-2 text-xs font-medium">Uploading {{ uploadProgress.current }} of {{ uploadProgress.total }}</p>
            <div class="mt-1 w-24 bg-muted rounded-full h-1.5">
              <div 
                class="bg-primary h-1.5 rounded-full transition-all duration-300"
                :style="{ width: uploadProgress.percentage + '%' }"
              ></div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- File Preview Section -->
      <div v-if="files.length > 0" class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-medium">Selected Files ({{ files.length }})</h3>
          <div class="flex gap-2">
            <Button variant="outline" size="sm" @click="clearAll" :disabled="isUploading" class="h-7 text-xs">
              <Trash2 class="w-3 h-3 mr-1.5" />
              Clear All
            </Button>
            <Button 
              @click="uploadFiles" 
              :disabled="isUploading || getPendingFiles().length === 0"
              class="bg-primary h-7 text-xs"
            >
              <LoaderCircle v-if="isUploading" class="w-3 h-3 mr-1.5 animate-spin" />
              <UploadCloud v-else class="w-3 h-3 mr-1.5" />
              {{ isUploading ? 'Uploading...' : `Upload ${getPendingFiles().length} File(s)` }}
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
                  file.status === 'uploading' ? 'bg-blue-500/80' : '',
                  file.status === 'success' ? 'bg-green-500/80' : '',
                  file.status === 'error' ? 'bg-red-500/80' : ''
                ]"
              >
                <LoaderCircle v-if="file.status === 'uploading'" class="w-3 h-3 animate-spin" />
                <CheckCircle v-else-if="file.status === 'success'" class="w-3 h-3" />
                <XCircle v-else-if="file.status === 'error'" class="w-3 h-3" />
              </div>
              
              <!-- Progress Bar -->
              <div 
                v-if="file.status === 'uploading'"
                class="absolute bottom-0 left-0 right-0 h-0.5 bg-white/20"
              >
                <div 
                  class="h-full bg-white transition-all duration-300"
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
      <div v-if="uploadStats.total > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3 p-3 bg-muted/50 rounded-lg">
        <div class="text-center">
          <div class="text-lg font-bold text-blue-600">{{ uploadStats.total }}</div>
          <div class="text-xs text-muted-foreground">Total Files</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-bold text-green-600">{{ uploadStats.success }}</div>
          <div class="text-xs text-muted-foreground">Successful</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-bold text-red-600">{{ uploadStats.failed }}</div>
          <div class="text-xs text-muted-foreground">Failed</div>
        </div>
        <div class="text-center">
          <div class="text-lg font-bold text-orange-600">{{ uploadStats.pending }}</div>
          <div class="text-xs text-muted-foreground">Pending</div>
        </div>
      </div>
    </CardContent>
  </Card>
  
  <!-- File Preview Modal -->
  <Dialog v-model:open="isPreviewOpen">
    <DialogContent class="max-w-4xl max-h-[90vh] overflow-auto">
      <DialogHeader>
        <DialogTitle class="text-base">File Preview</DialogTitle>
        <DialogDescription v-if="fileToPreview" class="text-xs">
          {{ fileToPreview.file.name }} ({{ formatFileSize(fileToPreview.file.size) }})
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
            <p class="text-xs">File will be processed after upload</p>
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
const uploadStats = computed(() => ({
  total: files.value.length,
  success: files.value.filter(f => f.status === 'success').length,
  failed: files.value.filter(f => f.status === 'error').length,
  pending: files.value.filter(f => f.status === 'pending').length,
  uploading: files.value.filter(f => f.status === 'uploading').length
}))

// File handling methods
const onDragOver = () => {
  isDragging.value = true
}

const onDragLeave = () => {
  isDragging.value = false
}

const onDrop = (event: DragEvent) => {
  isDragging.value = false
  const droppedFiles = event.dataTransfer?.files
  if (droppedFiles) {
    addFiles(droppedFiles)
  }
}

const onFileSelected = () => {
  const selectedFiles = fileInput.value?.files
  if (selectedFiles) {
    addFiles(selectedFiles)
  }
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const openFileDialog = () => {
  if (isUploading.value) return
  fileInput.value?.click()
}

const addFiles = (fileList: FileList) => {
  Array.from(fileList).forEach(file => {
    // Validate file
    const validation = validateFile(file)
    if (!validation.valid) {
      console.error(`File validation failed: ${validation.error}`)
      return
    }
    
    const uploadableFile: UploadableFile = {
      id: `file_${Math.random().toString(36).substr(2, 9)}`,
      file,
      progress: 0,
      status: 'pending',
      previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : undefined
    }
    
    files.value.push(uploadableFile)
    emit('fileAdded', file)
  })
}

const removeFile = (fileId: string) => {
  const fileIndex = files.value.findIndex(f => f.id === fileId)
  if (fileIndex > -1) {
    const file = files.value[fileIndex]
    if (file.previewUrl) {
      URL.revokeObjectURL(file.previewUrl)
    }
    files.value.splice(fileIndex, 1)
    emit('fileRemoved', fileId)
  }
}

const clearAll = () => {
  files.value.forEach(file => {
    if (file.previewUrl) {
      URL.revokeObjectURL(file.previewUrl)
    }
  })
  files.value = []
}

const getPendingFiles = () => {
  return files.value.filter(f => f.status === 'pending' || f.status === 'error')
}

const previewFile = (file: UploadableFile) => {
  fileToPreview.value = file
  isPreviewOpen.value = true
}

// Upload methods
const uploadFiles = async () => {
  const filesToUpload = getPendingFiles()
  if (filesToUpload.length === 0) return

  isUploading.value = true
  uploadProgress.value = {
    current: 0,
    total: filesToUpload.length,
    percentage: 0
  }

  const results = []

  for (let i = 0; i < filesToUpload.length; i++) {
    const fileToUpload = filesToUpload[i]
    
    try {
      fileToUpload.status = 'uploading'
      uploadProgress.value.current = i + 1
      uploadProgress.value.percentage = Math.round(((i + 1) / filesToUpload.length) * 100)
      
      const result = await uploadSingleFile(fileToUpload)
      fileToUpload.status = 'success'
      fileToUpload.progress = 100
      results.push(result)
      
    } catch (error) {
      fileToUpload.status = 'error'
      fileToUpload.error = error instanceof Error ? error.message : 'Upload failed'
      results.push({ error: fileToUpload.error, file: fileToUpload.file })
    }
  }

  isUploading.value = false
  emit('uploadComplete', results)
}

const uploadSingleFile = async (uploadableFile: UploadableFile): Promise<any> => {
  return new Promise((resolve, reject) => {
    const formData = new FormData()
    formData.append('file', uploadableFile.file)

    const xhr = new XMLHttpRequest()

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        uploadableFile.progress = Math.round((event.loaded / event.total) * 100)
      }
    }

    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText)
          resolve(response)
        } catch (e) {
          reject(new Error('Invalid response format'))
        }
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`))
      }
    }

    xhr.onerror = () => {
      reject(new Error('Upload failed'))
    }

    xhr.open('POST', '/api/upload')
    xhr.send(formData)
  })
}

// Validation
const validateFile = (file: File): { valid: boolean; error?: string } => {
  // Check file type
  const fileExtension = file.name.split('.').pop()?.toLowerCase()
  if (!fileExtension || !props.allowedFileTypes.includes(fileExtension)) {
    return {
      valid: false,
      error: `File type not allowed. Allowed types: ${props.allowedFileTypes.join(', ')}`
    }
  }

  // Check file size
  const maxSizeBytes = props.maxFileSize * 1024 * 1024
  if (file.size > maxSizeBytes) {
    return {
      valid: false,
      error: `File size too large. Maximum size: ${props.maxFileSize}MB`
    }
  }

  return { valid: true }
}

// Utility methods
const formatFileSize = (bytes: number): string => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }

  return `${size.toFixed(1)} ${units[unitIndex]}`
}

const getStatusVariant = (status: string) => {
  switch (status) {
    case 'success': return 'default'
    case 'error': return 'destructive'
    case 'uploading': return 'secondary'
    default: return 'outline'
  }
}

const getStatusText = (status: string): string => {
  switch (status) {
    case 'pending': return 'Ready'
    case 'uploading': return 'Uploading'
    case 'success': return 'Complete'
    case 'error': return 'Failed'
    default: return 'Unknown'
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