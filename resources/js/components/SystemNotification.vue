<template>
    <div class="fixed top-4 right-4 z-50">
        <div v-for="notification in notifications" :key="notification.id" 
             :class="['mb-3 p-4 rounded-lg shadow-lg border-l-4 max-w-sm transform transition-all duration-300 ease-in-out', 
                      notification.entering ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0',
                      getNotificationClass(notification.type)]">
            
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <component :is="getIcon(notification.type)" :class="['h-5 w-5', getIconClass(notification.type)]" />
                </div>
                
                <div class="ml-3 flex-1">
                    <h4 v-if="notification.title" class="text-sm font-medium mb-1">
                        {{ notification.title }}
                    </h4>
                    <p class="text-sm">
                        {{ notification.message }}
                    </p>
                    
                    <div v-if="notification.actions" class="flex space-x-2 mt-2">
                        <button 
                            v-for="action in notification.actions" 
                            :key="action.label"
                            @click="handleAction(action, notification)"
                            :class="['px-2 py-1 text-xs rounded hover:opacity-80 transition-opacity', 
                                    action.primary ? 'bg-primary text-primary-foreground' : 'bg-secondary text-secondary-foreground']"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </div>
                
                <div class="ml-4 flex-shrink-0">
                    <button @click="removeNotification(notification.id)" 
                            class="text-muted-foreground hover:text-foreground transition-colors">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>
            
            <!-- Progress bar for auto-dismiss -->
            <div v-if="notification.autoClose && notification.duration" 
                 class="mt-2 h-1 bg-background rounded-full overflow-hidden">
                <div class="h-full bg-primary rounded-full transition-all duration-linear" 
                     :style="{ width: `${notification.progress}%` }"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { CheckCircle, AlertCircle, XCircle, Info, X } from 'lucide-vue-next';

interface NotificationAction {
    label: string;
    primary?: boolean;
    action: () => void;
}

interface Notification {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    title?: string;
    message: string;
    duration?: number;
    autoClose?: boolean;
    actions?: NotificationAction[];
    entering?: boolean;
    progress?: number;
}

const notifications = ref<Notification[]>([]);
const intervals = new Map<string, number>();

const getNotificationClass = (type: string): string => {
    switch (type) {
        case 'success': return 'bg-green-50 border-green-400 text-green-800 dark:bg-green-900/20 dark:border-green-600 dark:text-green-200';
        case 'error': return 'bg-red-50 border-red-400 text-red-800 dark:bg-red-900/20 dark:border-red-600 dark:text-red-200';
        case 'warning': return 'bg-yellow-50 border-yellow-400 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-600 dark:text-yellow-200';
        case 'info': return 'bg-blue-50 border-blue-400 text-blue-800 dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-200';
        default: return 'bg-gray-50 border-gray-400 text-gray-800 dark:bg-gray-900/20 dark:border-gray-600 dark:text-gray-200';
    }
};

const getIcon = (type: string) => {
    switch (type) {
        case 'success': return CheckCircle;
        case 'error': return XCircle;
        case 'warning': return AlertCircle;
        case 'info': return Info;
        default: return Info;
    }
};

const getIconClass = (type: string): string => {
    switch (type) {
        case 'success': return 'text-green-600 dark:text-green-400';
        case 'error': return 'text-red-600 dark:text-red-400';
        case 'warning': return 'text-yellow-600 dark:text-yellow-400';
        case 'info': return 'text-blue-600 dark:text-blue-400';
        default: return 'text-gray-600 dark:text-gray-400';
    }
};

const addNotification = (notification: Omit<Notification, 'id' | 'entering' | 'progress'>) => {
    const id = Date.now().toString();
    const newNotification: Notification = {
        ...notification,
        id,
        entering: false,
        progress: 100,
        autoClose: notification.autoClose ?? true,
        duration: notification.duration ?? 5000,
    };
    
    notifications.value.push(newNotification);
    
    // Trigger enter animation
    setTimeout(() => {
        const notif = notifications.value.find(n => n.id === id);
        if (notif) {
            notif.entering = true;
        }
    }, 50);
    
    // Setup auto-close
    if (newNotification.autoClose && newNotification.duration) {
        const startTime = Date.now();
        const interval = setInterval(() => {
            const notif = notifications.value.find(n => n.id === id);
            if (!notif) {
                clearInterval(interval);
                return;
            }
            
            const elapsed = Date.now() - startTime;
            const progress = Math.max(0, 100 - (elapsed / newNotification.duration!) * 100);
            notif.progress = progress;
            
            if (elapsed >= newNotification.duration!) {
                removeNotification(id);
            }
        }, 100);
        
        intervals.set(id, interval);
    }
};

const removeNotification = (id: string) => {
    const index = notifications.value.findIndex(n => n.id === id);
    if (index > -1) {
        // Trigger exit animation
        notifications.value[index].entering = false;
        
        // Remove after animation
        setTimeout(() => {
            notifications.value.splice(index, 1);
        }, 300);
    }
    
    // Clear interval
    const interval = intervals.get(id);
    if (interval) {
        clearInterval(interval);
        intervals.delete(id);
    }
};

const handleAction = (action: NotificationAction, notification: Notification) => {
    action.action();
    removeNotification(notification.id);
};

const clearAll = () => {
    notifications.value.forEach(notification => {
        removeNotification(notification.id);
    });
};

// Global notification methods
const showSuccess = (message: string, options?: Partial<Notification>) => {
    addNotification({ type: 'success', message, ...options });
};

const showError = (message: string, options?: Partial<Notification>) => {
    addNotification({ type: 'error', message, autoClose: false, ...options });
};

const showWarning = (message: string, options?: Partial<Notification>) => {
    addNotification({ type: 'warning', message, ...options });
};

const showInfo = (message: string, options?: Partial<Notification>) => {
    addNotification({ type: 'info', message, ...options });
};

// Expose methods globally
defineExpose({
    showSuccess,
    showError,
    showWarning,
    showInfo,
    clearAll,
    addNotification,
    removeNotification,
});

// Global event listeners
onMounted(() => {
    // Listen for global notification events
    window.addEventListener('show-notification', (event: any) => {
        const { type, message, options } = event.detail;
        addNotification({ type, message, ...options });
    });
});

onUnmounted(() => {
    // Clear all intervals
    intervals.forEach(interval => clearInterval(interval));
    intervals.clear();
});
</script>

<style scoped>
.duration-linear {
    transition-timing-function: linear;
}
</style> 