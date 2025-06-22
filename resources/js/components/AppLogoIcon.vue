<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    src?: string;
    alt?: string;
}

const props = withDefaults(defineProps<Props>(), {
    src: '/logos/logo-white-circle.webp',
    alt: 'Tuah Plan Logo'
});
</script>

<template>
    <img 
        :src="src" 
        :alt="alt" 
        :class="className" 
        v-bind="$attrs"
    />
</template>
