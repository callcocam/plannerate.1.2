<script setup lang="ts">
import AppLayout from './layouts/AppLayout.vue';
import { type BreadcrumbItem } from './types'; 

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
    description: {
        type: String,
        default: 'Dashboard',
    },
    breadcrumbs: {
        type: Array as () => BreadcrumbItem[],
        default: () => [],
    },
}); 
</script>

<template> 
    <AppLayout :breadcrumbs="breadcrumbs">
        <router-view></router-view>
    </AppLayout>
</template>
