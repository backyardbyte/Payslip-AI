<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import SystemNotification from '@/components/SystemNotification.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppSidebarLayout>
        <slot />
    </AppSidebarLayout>
    
    <!-- System Notifications -->
    <SystemNotification ref="notifications" />
</template>
