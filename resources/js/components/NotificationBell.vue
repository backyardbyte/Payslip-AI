<template>
    <div class="relative">
        <!-- Notification Bell Button -->
        <Button
            variant="ghost"
            size="icon"
            class="relative"
            @click="toggleDropdown"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </Button>

        <!-- Notification Dropdown -->
        <div
            v-if="showDropdown"
            class="absolute right-0 top-full mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50"
            @click.stop
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-semibold text-gray-900">Notifications</h3>
                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="markAllAsRead"
                        :disabled="unreadCount === 0 || isMarkingAllRead"
                    >
                        <CheckCheck class="w-4 h-4 mr-1" />
                        Mark all read
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="refreshNotifications"
                        :disabled="isRefreshing"
                    >
                        <RefreshCw :class="['w-4 h-4', isRefreshing && 'animate-spin']" />
                    </Button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center p-8">
                <LoaderCircle class="h-6 w-6 animate-spin text-gray-400" />
                <span class="ml-2 text-gray-500">Loading notifications...</span>
            </div>

            <!-- Notifications List -->
            <div v-else-if="notifications.length > 0" class="max-h-96 overflow-y-auto">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    :class="[
                        'p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors',
                        !notification.read_at && 'bg-blue-50'
                    ]"
                    @click="markAsRead(notification)"
                >
                    <div class="flex items-start gap-3">
                        <!-- Icon -->
                        <div :class="[
                            'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center',
                            getNotificationColor(notification.data.type)
                        ]">
                            <component :is="getNotificationIcon(notification.data.icon)" class="w-4 h-4" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ notification.data.title }}
                                </p>
                                <div class="flex items-center gap-1">
                                    <span v-if="!notification.read_at" class="w-2 h-2 bg-blue-500 rounded-full"></span>
                                    <span class="text-xs text-gray-500">
                                        {{ formatRelativeTime(notification.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">
                                {{ notification.data.message }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center p-8 text-center">
                <Bell class="h-12 w-12 text-gray-300 mb-4" />
                <h3 class="text-sm font-medium text-gray-900">No notifications</h3>
                <p class="text-sm text-gray-500 mt-1">You're all caught up!</p>
            </div>

            <!-- Footer -->
            <div class="p-3 border-t bg-gray-50">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full justify-center"
                    @click="viewAllNotifications"
                >
                    View all notifications
                </Button>
            </div>
        </div>

        <!-- Backdrop -->
        <div
            v-if="showDropdown"
            class="fixed inset-0 z-40"
            @click="showDropdown = false"
        ></div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Button } from '@/components/ui/button'
import {
    Bell, CheckCheck, RefreshCw, LoaderCircle, CheckCircle, XCircle,
    FileCheck, FileX, Settings, ShieldAlert, Key, Lock, AlertCircle
} from 'lucide-vue-next'

interface Notification {
    id: string
    type: string
    data: {
        type: string
        title: string
        message: string
        icon: string
        priority: string
        action_url?: string
        action_text?: string
    }
    read_at: string | null
    created_at: string
}

const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const showDropdown = ref(false)
const isLoading = ref(false)
const isRefreshing = ref(false)
const isMarkingAllRead = ref(false)

let pollingInterval: number | undefined

const toggleDropdown = async () => {
    showDropdown.value = !showDropdown.value
    if (showDropdown.value && notifications.value.length === 0) {
        await fetchNotifications()
    }
}

const fetchNotifications = async () => {
    try {
        isLoading.value = true
        const response = await fetch('/api/notifications/recent', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        })
        if (response.ok) {
            const data = await response.json()
            notifications.value = data.data.notifications || []
            unreadCount.value = data.data.unread_count || 0
        }
    } catch (error) {
        console.error('Failed to fetch notifications:', error)
    } finally {
        isLoading.value = false
    }
}

const refreshNotifications = async () => {
    isRefreshing.value = true
    await fetchNotifications()
    isRefreshing.value = false
}

const markAsRead = async (notification: Notification) => {
    if (notification.read_at) return

    try {
        const response = await fetch(`/api/notifications/${notification.id}/read`, {
            method: 'POST'
        })
        
        if (response.ok) {
            notification.read_at = new Date().toISOString()
            unreadCount.value = Math.max(0, unreadCount.value - 1)
            
            // Navigate to action URL if available
            if (notification.data.action_url) {
                window.location.href = notification.data.action_url
            }
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error)
    }
}

const markAllAsRead = async () => {
    if (unreadCount.value === 0) return

    try {
        isMarkingAllRead.value = true
        const response = await fetch('/api/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        })
        
        if (response.ok) {
            notifications.value.forEach(notification => {
                if (!notification.read_at) {
                    notification.read_at = new Date().toISOString()
                }
            })
            unreadCount.value = 0
        }
    } catch (error) {
        console.error('Failed to mark all notifications as read:', error)
    } finally {
        isMarkingAllRead.value = false
    }
}

const viewAllNotifications = () => {
    showDropdown.value = false
    // Navigate to notifications page (to be implemented)
    window.location.href = '/notifications'
}

const getNotificationIcon = (iconName: string) => {
    const iconMap: Record<string, any> = {
        'check-circle': CheckCircle,
        'x-circle': XCircle,
        'file-check': FileCheck,
        'file-x': FileX,
        'settings': Settings,
        'shield-alert': ShieldAlert,
        'key': Key,
        'lock': Lock,
        'bell': Bell,
        'alert-circle': AlertCircle,
    }
    
    return iconMap[iconName] || Bell
}

const getNotificationColor = (type: string) => {
    switch (type) {
        case 'batch_completed':
        case 'payslip_processed':
            return 'bg-green-100 text-green-600'
        case 'batch_failed':
        case 'payslip_failed':
            return 'bg-red-100 text-red-600'
        case 'system_maintenance':
            return 'bg-yellow-100 text-yellow-600'
        case 'login_alert':
        case 'password_changed':
        case 'account_locked':
            return 'bg-blue-100 text-blue-600'
        default:
            return 'bg-gray-100 text-gray-600'
    }
}

const formatRelativeTime = (dateString: string) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now.getTime() - date.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMins / 60)
    const diffDays = Math.floor(diffHours / 24)

    if (diffMins < 1) return 'Just now'
    if (diffMins < 60) return `${diffMins}m ago`
    if (diffHours < 24) return `${diffHours}h ago`
    return `${diffDays}d ago`
}

onMounted(async () => {
    await fetchNotifications()
    
    // Poll for new notifications every 30 seconds
    pollingInterval = setInterval(fetchNotifications, 30000)
})

onUnmounted(() => {
    if (pollingInterval) {
        clearInterval(pollingInterval)
    }
})
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style> 