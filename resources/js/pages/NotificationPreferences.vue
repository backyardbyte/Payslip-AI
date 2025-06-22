<template>
    <Head title="Notification Preferences" />
    
    <AppLayout>
        <div class="max-w-4xl mx-auto p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Notification Preferences</h1>
                    <p class="text-gray-600 mt-1">Manage how and when you receive notifications</p>
                </div>
                <Button @click="savePreferences" :disabled="isSaving">
                    <LoaderCircle v-if="isSaving" class="w-4 h-4 mr-2 animate-spin" />
                    Save Changes
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center p-12">
                <LoaderCircle class="h-8 w-8 animate-spin text-gray-400" />
                <span class="ml-3 text-gray-600">Loading preferences...</span>
            </div>

            <!-- Preferences Form -->
            <div v-else class="space-y-6">
                <!-- Event Preferences -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bell class="h-5 w-5" />
                            Event Preferences
                        </CardTitle>
                        <CardDescription>
                            Configure notifications for different types of events
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div
                                v-for="(eventName, eventKey) in availableEvents"
                                :key="eventKey"
                                class="border rounded-lg p-6"
                            >
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                        <Bell class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">{{ eventName }}</h3>
                                        <p class="text-sm text-gray-500">
                                            Configure how you receive these notifications
                                        </p>
                                    </div>
                                </div>

                                <!-- Channel Toggles -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div
                                        v-for="(channelName, channelKey) in availableChannels"
                                        :key="`${eventKey}-${channelKey}`"
                                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Bell class="w-4 h-4 text-gray-500" />
                                            <span class="text-sm font-medium">{{ channelName }}</span>
                                        </div>
                                        <Checkbox
                                            :checked="isPreferenceEnabled(eventKey, channelKey)"
                                            @update:checked="updatePreference(eventKey, channelKey, $event)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Quick Actions -->
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>
                            Quickly configure common notification scenarios
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Button
                                variant="outline"
                                class="h-auto p-4 flex flex-col items-center gap-2"
                                @click="enableAllNotifications"
                            >
                                <CheckCircle class="w-6 h-6 text-green-500" />
                                <span class="font-medium">Enable All</span>
                                <span class="text-xs text-gray-500">Turn on all notifications</span>
                            </Button>
                            
                            <Button
                                variant="outline"
                                class="h-auto p-4 flex flex-col items-center gap-2"
                                @click="enableEssentialOnly"
                            >
                                <Shield class="w-6 h-6 text-blue-500" />
                                <span class="font-medium">Essential Only</span>
                                <span class="text-xs text-gray-500">Critical notifications only</span>
                            </Button>
                            
                            <Button
                                variant="outline"
                                class="h-auto p-4 flex flex-col items-center gap-2"
                                @click="disableAllNotifications"
                            >
                                <XCircle class="w-6 h-6 text-red-500" />
                                <span class="font-medium">Disable All</span>
                                <span class="text-xs text-gray-500">Turn off all notifications</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Bell, LoaderCircle, CheckCircle, XCircle, Shield } from 'lucide-vue-next'

interface Preference {
    event_type: string
    channel: string
    enabled: boolean
    settings?: any
}

const preferences = ref<Record<string, Record<string, boolean>>>({})
const availableEvents = ref<Record<string, string>>({})
const availableChannels = ref<Record<string, string>>({})
const isLoading = ref(true)
const isSaving = ref(false)

const fetchPreferences = async () => {
    try {
        const response = await fetch('/api/notifications/preferences', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            }
        })
        if (response.ok) {
            const data = await response.json()
            
            // Transform preferences data
            const prefs: Record<string, Record<string, boolean>> = {}
            Object.entries(data.data.preferences || {}).forEach(([eventType, channels]: [string, any]) => {
                prefs[eventType] = {}
                Object.entries(channels).forEach(([channel, preference]: [string, any]) => {
                    prefs[eventType][channel] = preference.enabled || false
                })
            })
            
            preferences.value = prefs
            availableEvents.value = data.data.available_events || {}
            availableChannels.value = data.data.available_channels || {}
        }
    } catch (error) {
        console.error('Failed to fetch preferences:', error)
    } finally {
        isLoading.value = false
    }
}

const isPreferenceEnabled = (eventType: string, channel: string): boolean => {
    return preferences.value[eventType]?.[channel] || false
}

const updatePreference = (eventType: string, channel: string, enabled: boolean) => {
    if (!preferences.value[eventType]) {
        preferences.value[eventType] = {}
    }
    preferences.value[eventType][channel] = enabled
}

const savePreferences = async () => {
    try {
        isSaving.value = true
        
        // Transform preferences to API format
        const preferencesToSave: Preference[] = []
        Object.entries(preferences.value).forEach(([eventType, channels]) => {
            Object.entries(channels).forEach(([channel, enabled]) => {
                preferencesToSave.push({
                    event_type: eventType,
                    channel,
                    enabled
                })
            })
        })
        
        const response = await fetch('/api/notifications/preferences', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                preferences: preferencesToSave
            })
        })
        
        if (response.ok) {
            console.log('Preferences saved successfully')
        }
    } catch (error) {
        console.error('Failed to save preferences:', error)
    } finally {
        isSaving.value = false
    }
}

const enableAllNotifications = () => {
    Object.keys(availableEvents.value).forEach(eventType => {
        Object.keys(availableChannels.value).forEach(channel => {
            updatePreference(eventType, channel, true)
        })
    })
}

const enableEssentialOnly = () => {
    const essentialEvents = ['batch_failed', 'payslip_failed', 'system_maintenance', 'login_alert']
    const essentialChannels = ['in_app', 'email']
    
    // Disable all first
    disableAllNotifications()
    
    // Enable essential ones
    essentialEvents.forEach(eventType => {
        if (availableEvents.value[eventType]) {
            essentialChannels.forEach(channel => {
                if (availableChannels.value[channel]) {
                    updatePreference(eventType, channel, true)
                }
            })
        }
    })
}

const disableAllNotifications = () => {
    Object.keys(availableEvents.value).forEach(eventType => {
        Object.keys(availableChannels.value).forEach(channel => {
            updatePreference(eventType, channel, false)
        })
    })
}

onMounted(async () => {
    await fetchPreferences()
})
</script> 