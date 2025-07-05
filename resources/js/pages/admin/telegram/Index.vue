<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { 
    Play, 
    Square, 
    RotateCcw, 
    Activity, 
    Settings, 
    Eye, 
    Trash2, 
    TestTube, 
    RefreshCw, 
    CheckCircle, 
    XCircle, 
    Clock, 
    Users, 
    MessageCircle, 
    Zap,
    AlertCircle,
    Download,
    Upload,
    Terminal,
    Database,
    Wifi,
    WifiOff
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'

interface BotStatus {
    is_running: boolean
    status: string
    pid: number | null
    uptime: string | null
    last_checked: string
    error?: string
}

interface BotInfo {
    is_configured: boolean
    has_webhook: boolean
    use_simple_mode: boolean
    token_configured: boolean
    webhook_url: string | null
    polling_interval: number
    rate_limit: number
    debug_mode: boolean
    auto_restart: boolean
    error?: string
}

interface BotStatistics {
    total_users: number
    total_messages: number
    messages_last_24h: number
    active_users: number
    last_updated: string
    error?: string
}

interface BotConfiguration {
    use_simple_bot: boolean
    polling_interval: number
    rate_limit: number
    debug_mode: boolean
    auto_restart: boolean
    max_consecutive_errors: number
    timeout: number
    error?: string
}

interface Props {
    botStatus: BotStatus
    botInfo: BotInfo
    statistics: BotStatistics
    configuration: BotConfiguration
    permissions: {
        canManageTelegram: boolean
        canViewTelegramLogs: boolean
        canConfigureTelegram: boolean
    }
}

const props = defineProps<Props>()

const currentBotStatus = ref<BotStatus>(props.botStatus)
const currentBotInfo = ref<BotInfo>(props.botInfo)
const currentStatistics = ref<BotStatistics>(props.statistics)
const currentConfiguration = ref<BotConfiguration>(props.configuration)

const loading = reactive({
    start: false,
    stop: false,
    restart: false,
    status: false,
    logs: false,
    test: false,
    config: false,
    cache: false
})

const logs = ref<string[]>([])
const showConfigDialog = ref(false)
const showLogsDialog = ref(false)
const autoRefresh = ref(false)
const refreshInterval = ref<number | null>(null)

const configForm = reactive({
    use_simple_bot: props.configuration.use_simple_bot,
    polling_interval: props.configuration.polling_interval,
    rate_limit: props.configuration.rate_limit,
    debug_mode: props.configuration.debug_mode,
    auto_restart: props.configuration.auto_restart
})

onMounted(() => {
    // Start auto-refresh if enabled
    if (autoRefresh.value) {
        startAutoRefresh()
    }
})

onUnmounted(() => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
    }
})

const startBot = async () => {
    loading.start = true
    try {
        const response = await fetch(route('admin.telegram.start'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                simple: configForm.use_simple_bot
            })
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Telegram bot started successfully!')
            await refreshStatus()
        } else {
            console.error(data.message || 'Failed to start telegram bot')
        }
    } catch (error) {
        console.error('Error starting telegram bot')
        console.error('Error starting bot:', error)
    } finally {
        loading.start = false
    }
}

const stopBot = async () => {
    loading.stop = true
    try {
        const response = await fetch(route('admin.telegram.stop'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Telegram bot stopped successfully!')
            await refreshStatus()
        } else {
            console.error(data.message || 'Failed to stop telegram bot')
        }
    } catch (error) {
        console.error('Error stopping telegram bot')
        console.error('Error stopping bot:', error)
    } finally {
        loading.stop = false
    }
}

const restartBot = async () => {
    loading.restart = true
    try {
        const response = await fetch(route('admin.telegram.restart'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                simple: configForm.use_simple_bot
            })
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Telegram bot restarted successfully!')
            await refreshStatus()
        } else {
            console.error(data.message || 'Failed to restart telegram bot')
        }
    } catch (error) {
        console.error('Error restarting telegram bot')
        console.error('Error restarting bot:', error)
    } finally {
        loading.restart = false
    }
}

const refreshStatus = async () => {
    loading.status = true
    try {
        const response = await fetch(route('admin.telegram.status'))
        const data = await response.json()
        
        if (data.success) {
            currentBotStatus.value = data.data
        } else {
            console.error('Failed to refresh bot status')
        }
    } catch (error) {
        console.error('Error refreshing bot status')
        console.error('Error refreshing status:', error)
    } finally {
        loading.status = false
    }
}

const loadLogs = async () => {
    loading.logs = true
    try {
        const response = await fetch(route('admin.telegram.logs') + '?lines=100')
        const data = await response.json()
        
        if (data.success) {
            logs.value = data.data.logs || []
        } else {
            console.error('Failed to load logs')
        }
    } catch (error) {
        console.error('Error loading logs')
        console.error('Error loading logs:', error)
    } finally {
        loading.logs = false
    }
}

const clearLogs = async () => {
    try {
        const response = await fetch(route('admin.telegram.logs.clear'), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Logs cleared successfully!')
            logs.value = []
        } else {
            console.error(data.message || 'Failed to clear logs')
        }
    } catch (error) {
        console.error('Error clearing logs')
        console.error('Error clearing logs:', error)
    }
}

const testConnection = async () => {
    loading.test = true
    try {
        const response = await fetch(route('admin.telegram.test'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Bot connection test successful!')
        } else {
            console.error(data.message || 'Bot connection test failed')
        }
    } catch (error) {
        console.error('Error testing bot connection')
        console.error('Error testing connection:', error)
    } finally {
        loading.test = false
    }
}

const updateConfiguration = async () => {
    loading.config = true
    try {
        const response = await fetch(route('admin.telegram.config'), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(configForm)
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('Configuration updated successfully!')
            showConfigDialog.value = false
            // Update current configuration
            Object.assign(currentConfiguration.value, configForm)
        } else {
            console.error(data.message || 'Failed to update configuration')
        }
    } catch (error) {
        console.error('Error updating configuration')
        console.error('Error updating config:', error)
    } finally {
        loading.config = false
    }
}

const resetCache = async () => {
    loading.cache = true
    try {
        const response = await fetch(route('admin.telegram.reset-cache'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })

        const data = await response.json()
        
        if (data.success) {
            console.log('System cache reset successfully!')
            console.log('Cache reset details:', data.data)
            // Refresh the page data after cache reset
            await refreshStatus()
        } else {
            console.error(data.message || 'Failed to reset cache')
        }
    } catch (error) {
        console.error('Error resetting cache')
        console.error('Error resetting cache:', error)
    } finally {
        loading.cache = false
    }
}

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value
    if (autoRefresh.value) {
        startAutoRefresh()
    } else {
        if (refreshInterval.value) {
            clearInterval(refreshInterval.value)
        }
    }
}

const startAutoRefresh = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value)
    }
    refreshInterval.value = setInterval(refreshStatus, 5000) // Refresh every 5 seconds
}

const getStatusColor = (status: string) => {
    switch (status) {
        case 'running': return 'bg-green-500'
        case 'stopped': return 'bg-red-500'
        case 'unknown': return 'bg-yellow-500'
        default: return 'bg-gray-500'
    }
}

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'running': return CheckCircle
        case 'stopped': return XCircle
        case 'unknown': return AlertCircle
        default: return Clock
    }
}

const formatUptime = (uptime: string | null): string => {
    if (!uptime) return 'N/A'
    return uptime
}

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString()
}
</script>

<template>
    <Head title="Telegram Bot Management" />
    
    <AppLayout>
        <div class="flex flex-col h-full gap-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Telegram Bot Management</h1>
                    <p class="text-muted-foreground">Monitor and control your Telegram bot</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="toggleAutoRefresh"
                        :class="autoRefresh ? 'bg-green-50 border-green-200' : ''"
                    >
                        <RefreshCw :class="['h-4 w-4 mr-2', autoRefresh ? 'animate-spin' : '']" />
                        Auto Refresh
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="refreshStatus"
                        :disabled="loading.status"
                    >
                        <RefreshCw :class="['h-4 w-4 mr-2', loading.status ? 'animate-spin' : '']" />
                        Refresh
                    </Button>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Bot Status Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Bot Status</CardTitle>
                        <component :is="getStatusIcon(currentBotStatus.status)" class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <div :class="['w-2 h-2 rounded-full', getStatusColor(currentBotStatus.status)]"></div>
                            <Badge 
                                :variant="currentBotStatus.is_running ? 'default' : 'secondary'"
                                class="capitalize"
                            >
                                {{ currentBotStatus.status }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            {{ currentBotStatus.is_running ? 'Running' : 'Stopped' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Uptime Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Uptime</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ formatUptime(currentBotStatus.uptime) }}</div>
                        <p class="text-xs text-muted-foreground">
                            PID: {{ currentBotStatus.pid || 'N/A' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Total Users Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ currentStatistics.total_users }}</div>
                        <p class="text-xs text-muted-foreground">
                            Active: {{ currentStatistics.active_users }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Messages Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Messages (24h)</CardTitle>
                        <MessageCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ currentStatistics.messages_last_24h }}</div>
                        <p class="text-xs text-muted-foreground">
                            Total: {{ currentStatistics.total_messages }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Bot Control -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Zap class="h-5 w-5" />
                            Bot Control
                        </CardTitle>
                        <CardDescription>
                            Start, stop, or restart the Telegram bot
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Configuration Status -->
                        <div class="flex items-center justify-between p-3 bg-muted rounded-lg">
                            <div class="flex items-center gap-2">
                                <component :is="currentBotInfo.is_configured ? Wifi : WifiOff" class="h-4 w-4" />
                                <span class="text-sm">Configuration</span>
                            </div>
                            <Badge :variant="currentBotInfo.is_configured ? 'default' : 'destructive'">
                                {{ currentBotInfo.is_configured ? 'OK' : 'Not Configured' }}
                            </Badge>
                        </div>

                        <!-- Control Buttons -->
                        <div class="flex gap-2">
                            <Button
                                @click="startBot"
                                :disabled="loading.start || currentBotStatus.is_running || !currentBotInfo.is_configured"
                                class="flex-1"
                            >
                                <Play class="h-4 w-4 mr-2" />
                                Start Bot
                            </Button>
                            <Button
                                @click="stopBot"
                                :disabled="loading.stop || !currentBotStatus.is_running"
                                variant="outline"
                                class="flex-1"
                            >
                                <Square class="h-4 w-4 mr-2" />
                                Stop Bot
                            </Button>
                            <Button
                                @click="restartBot"
                                :disabled="loading.restart || !currentBotInfo.is_configured"
                                variant="outline"
                                class="flex-1"
                            >
                                <RotateCcw class="h-4 w-4 mr-2" />
                                Restart
                            </Button>
                        </div>

                        <!-- Test Connection -->
                        <Button
                            @click="testConnection"
                            :disabled="loading.test || !currentBotInfo.is_configured"
                            variant="outline"
                            class="w-full"
                        >
                            <TestTube class="h-4 w-4 mr-2" />
                            Test Connection
                        </Button>

                        <!-- Reset Cache -->
                        <Button
                            @click="resetCache"
                            :disabled="loading.cache"
                            variant="outline"
                            class="w-full"
                        >
                            <RefreshCw :class="['h-4 w-4 mr-2', loading.cache ? 'animate-spin' : '']" />
                            Reset System Cache
                        </Button>
                    </CardContent>
                </Card>

                <!-- Bot Configuration -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="h-5 w-5" />
                            Configuration
                        </CardTitle>
                        <CardDescription>
                            Current bot configuration and settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Mode</Label>
                                <p class="text-sm">{{ currentBotInfo.use_simple_mode ? 'Simple' : 'Advanced' }}</p>
                            </div>
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Polling Interval</Label>
                                <p class="text-sm">{{ currentBotInfo.polling_interval }}μs</p>
                            </div>
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Rate Limit</Label>
                                <p class="text-sm">{{ currentBotInfo.rate_limit }} msg/min</p>
                            </div>
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Debug Mode</Label>
                                <p class="text-sm">{{ currentBotInfo.debug_mode ? 'Enabled' : 'Disabled' }}</p>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex gap-2">
                            <Dialog v-model:open="showConfigDialog">
                                <DialogTrigger asChild>
                                    <Button variant="outline" class="flex-1">
                                        <Settings class="h-4 w-4 mr-2" />
                                        Configure
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[425px]">
                                    <DialogHeader>
                                        <DialogTitle>Bot Configuration</DialogTitle>
                                        <DialogDescription>
                                            Update telegram bot settings
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="grid gap-4 py-4">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox
                                                id="use-simple-bot"
                                                v-model:checked="configForm.use_simple_bot"
                                            />
                                            <Label for="use-simple-bot">Use Simple Bot Mode</Label>
                                        </div>
                                        <div class="grid grid-cols-4 items-center gap-4">
                                            <Label for="polling-interval" class="text-right">
                                                Polling Interval
                                            </Label>
                                            <Input
                                                id="polling-interval"
                                                v-model="configForm.polling_interval"
                                                type="number"
                                                min="100"
                                                max="10000"
                                                class="col-span-3"
                                            />
                                        </div>
                                        <div class="grid grid-cols-4 items-center gap-4">
                                            <Label for="rate-limit" class="text-right">
                                                Rate Limit
                                            </Label>
                                            <Input
                                                id="rate-limit"
                                                v-model="configForm.rate_limit"
                                                type="number"
                                                min="1"
                                                max="100"
                                                class="col-span-3"
                                            />
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox
                                                id="debug-mode"
                                                v-model:checked="configForm.debug_mode"
                                            />
                                            <Label for="debug-mode">Debug Mode</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <Checkbox
                                                id="auto-restart"
                                                v-model:checked="configForm.auto_restart"
                                            />
                                            <Label for="auto-restart">Auto Restart</Label>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            @click="updateConfiguration"
                                            :disabled="loading.config"
                                        >
                                            Save Configuration
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>

                            <Dialog v-model:open="showLogsDialog">
                                <DialogTrigger asChild>
                                    <Button variant="outline" class="flex-1">
                                        <Eye class="h-4 w-4 mr-2" />
                                        View Logs
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-[700px] sm:max-h-[600px]">
                                    <DialogHeader>
                                        <DialogTitle>Bot Logs</DialogTitle>
                                        <DialogDescription>
                                            Recent telegram bot log entries
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-4">
                                        <div class="flex gap-2">
                                            <Button
                                                @click="loadLogs"
                                                :disabled="loading.logs"
                                                variant="outline"
                                                size="sm"
                                            >
                                                <RefreshCw :class="['h-4 w-4 mr-2', loading.logs ? 'animate-spin' : '']" />
                                                Refresh
                                            </Button>
                                            <Button
                                                @click="clearLogs"
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Trash2 class="h-4 w-4 mr-2" />
                                                Clear Logs
                                            </Button>
                                        </div>
                                        <div class="h-[400px] w-full border rounded-md p-4 overflow-y-auto">
                                            <div v-if="logs.length === 0" class="text-center text-muted-foreground py-8">
                                                No logs available
                                            </div>
                                            <div v-else class="space-y-2">
                                                <div
                                                    v-for="(log, index) in logs"
                                                    :key="index"
                                                    class="text-xs font-mono bg-muted p-2 rounded"
                                                >
                                                    {{ log }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Information Cards -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Bot Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Database class="h-5 w-5" />
                            Bot Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Token Status</Label>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="currentBotInfo.token_configured ? 'default' : 'destructive'">
                                        {{ currentBotInfo.token_configured ? 'Configured' : 'Missing' }}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Webhook</Label>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="currentBotInfo.has_webhook ? 'default' : 'secondary'">
                                        {{ currentBotInfo.has_webhook ? 'Enabled' : 'Disabled' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <div v-if="currentBotInfo.webhook_url" class="space-y-2">
                            <Label class="text-xs font-medium text-muted-foreground">Webhook URL</Label>
                            <p class="text-xs font-mono bg-muted p-2 rounded break-all">
                                {{ currentBotInfo.webhook_url }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- System Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Activity class="h-5 w-5" />
                            System Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Last Status Check</Label>
                                <p class="text-xs">{{ formatDate(currentBotStatus.last_checked) }}</p>
                            </div>
                            <div>
                                <Label class="text-xs font-medium text-muted-foreground">Statistics Updated</Label>
                                <p class="text-xs">{{ formatDate(currentStatistics.last_updated) }}</p>
                            </div>
                        </div>

                        <Separator />

                        <div class="text-xs text-muted-foreground">
                            <p>Use the control buttons above to manage the bot lifecycle.</p>
                            <p>Auto-refresh is {{ autoRefresh ? 'enabled' : 'disabled' }}.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Alerts -->
            <div v-if="currentBotStatus.error || currentBotInfo.error" class="space-y-4">
                <div v-if="currentBotStatus.error" class="flex items-center gap-2 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <AlertCircle class="h-4 w-4" />
                    <div>
                        <strong>Bot Status Error:</strong> {{ currentBotStatus.error }}
                    </div>
                </div>
                <div v-if="currentBotInfo.error" class="flex items-center gap-2 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800">
                    <AlertCircle class="h-4 w-4" />
                    <div>
                        <strong>Bot Configuration Error:</strong> {{ currentBotInfo.error }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style> 